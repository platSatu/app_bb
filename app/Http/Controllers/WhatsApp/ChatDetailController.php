<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaDevice;
use App\Models\WaLabel;
use App\Services\Customer\CustomerService;
use App\Services\WhatsApp\InboxPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Panel kanan inbox: assignment, label, media & file, catatan, simpan ke Buku Kontak. */
class ChatDetailController extends Controller
{
    use InWhatsAppArea;

    public function show(Request $request, string $device, string $chat): JsonResponse
    {
        [$model, $thread] = $this->resolve($request, $device, $chat);

        return response()->json([
            'labels' => $this->labels($model, $thread),
            'notes' => $this->notes($thread),
            'assigned_user_id' => $thread->assigned_user_id,
            'bot_paused_until' => $thread->bot_paused_until?->isFuture() ? $thread->bot_paused_until->translatedFormat('d M H:i') : null,
            'team' => $this->assignableUsers($model),
            'contact' => $this->contact($model, $thread),
            'groups' => ContactGroup::where('branch_id', $model->branch_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function media(Request $request, string $device, string $chat, InboxPresenter $present): JsonResponse
    {
        [$model, $thread] = $this->resolve($request, $device, $chat);
        $type = $request->validate(['type' => ['required', Rule::in(['image', 'video', 'document'])]])['type'];

        $items = $thread->messages()->where('type', $type)
            ->where(fn ($q) => $q->whereNotNull('media_path')->orWhereNotNull('media_meta'))
            ->latest('sent_at')->limit(30)->get();

        return response()->json(['items' => $items->map(fn ($m) => $present->message($model, $m))]);
    }

    public function attachLabel(Request $request, string $device, string $chat, string $label): JsonResponse
    {
        [$model, $thread] = $this->resolve($request, $device, $chat);
        $thread->labels()->syncWithoutDetaching([$this->label($model, $label)->id]);

        return response()->json(['labels' => $this->labels($model, $thread)]);
    }

    public function detachLabel(Request $request, string $device, string $chat, string $label): JsonResponse
    {
        [$model, $thread] = $this->resolve($request, $device, $chat);
        $thread->labels()->detach($this->label($model, $label)->id);

        return response()->json(['labels' => $this->labels($model, $thread)]);
    }

    public function storeNote(Request $request, string $device, string $chat): JsonResponse
    {
        [, $thread] = $this->resolve($request, $device, $chat);
        $note = $thread->notes()->make($request->validate(['note' => ['required', 'string', 'max:2000']]));
        $note->forceFill(['user_id' => $request->user()->id])->save();

        return response()->json(['notes' => $this->notes($thread)], Response::HTTP_CREATED);
    }

    public function updateNote(Request $request, string $device, string $chat, string $note): JsonResponse
    {
        [, $thread] = $this->resolve($request, $device, $chat);
        $thread->notes()->findOrFail($note)->update($request->validate(['note' => ['required', 'string', 'max:2000']]));

        return response()->json(['notes' => $this->notes($thread)]);
    }

    public function destroyNote(Request $request, string $device, string $chat, string $note): JsonResponse
    {
        [, $thread] = $this->resolve($request, $device, $chat);
        $thread->notes()->findOrFail($note)->delete();

        return response()->json(['notes' => $this->notes($thread)]);
    }

    public function assign(Request $request, string $device, string $chat): JsonResponse
    {
        [$model, $thread] = $this->resolve($request, $device, $chat);
        $userId = $request->validate([
            'user_id' => ['nullable', Rule::in($this->assignableUsers($model)->pluck('id'))],
        ])['user_id'] ?? null;

        $thread->forceFill(['assigned_user_id' => $userId])->save();

        return response()->json(['assigned_user_id' => $userId, 'message' => $userId ? 'Chat sudah ditugaskan.' : 'Penugasan chat dilepas.']);
    }

    /** Jeda bot untuk chat ini (admin mengambil alih) atau aktifkan lagi. */
    public function bot(Request $request, string $device, string $chat): JsonResponse
    {
        [, $thread] = $this->resolve($request, $device, $chat);
        $hours = (int) $request->validate(['hours' => ['required', 'integer', 'min:0', 'max:720']])['hours'];

        $thread->forceFill(['bot_paused_until' => $hours ? now()->addHours($hours) : null, 'human_replied_at' => $hours ? $thread->human_replied_at : null])->save();

        return response()->json([
            'bot_paused_until' => $hours ? $thread->bot_paused_until->translatedFormat('d M H:i') : null,
            'message' => $hours ? "Bot dijeda untuk chat ini selama {$hours} jam." : 'Bot aktif lagi untuk chat ini.',
        ]);
    }

    /** Simpan nomor chat ke Buku Kontak branch, opsional langsung masuk Group. */
    public function saveContact(Request $request, string $device, string $chat, CustomerService $customers): JsonResponse
    {
        [$model, $thread] = $this->resolve($request, $device, $chat);
        abort_if($thread->is_group || ! $thread->phone, 422, 'Chat ini belum punya nomor HP yang bisa disimpan.');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'group_id' => ['nullable', Rule::exists('contact_groups', 'id')->where('branch_id', $model->branch_id)],
        ]);

        $customer = DB::transaction(function () use ($model, $thread, $data, $customers) {
            $branch = UserBranch::whereKey($model->branch_id)->lockForUpdate()->firstOrFail();
            $customer = $customers->resolve($branch, $data['name'] ?? null, $thread->phone, null, 'whatsapp');
            if (! $customer->name && $thread->name) {
                $customer->forceFill(['name' => $thread->name])->save();
            }
            if (! empty($data['group_id'])) {
                $customer->groups()->syncWithoutDetaching([$data['group_id'] => ['created_at' => now()]]);
            }

            return $customer;
        });

        return response()->json(['contact' => $this->contactPayload($customer), 'message' => 'Kontak tersimpan di Buku Kontak.']);
    }

    /** vCard untuk disimpan ke kontak HP. */
    public function vcard(Request $request, string $device, string $chat): Response
    {
        [, $thread] = $this->resolve($request, $device, $chat);
        abort_if($thread->is_group || ! $thread->phone, 404);

        $name = str_replace(["\r", "\n", ';', ','], ' ', $thread->name ?: '+'.$thread->phone);
        $card = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:{$name}\r\nTEL;TYPE=CELL:+{$thread->phone}\r\nEND:VCARD\r\n";

        return response($card, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="kontak-'.$thread->phone.'.vcf"',
        ]);
    }

    private function resolve(Request $request, string $device, string $chat): array
    {
        $model = $this->device($request, $device);

        return [$model, $this->chat($model, $chat)];
    }

    private function label(WaDevice $device, string $id): WaLabel
    {
        return WaLabel::where('branch_id', $device->branch_id)->findOrFail($id);
    }

    private function labels(WaDevice $device, WaChat $chat): array
    {
        $assigned = $chat->labels()->pluck('wa_labels.id')->all();

        return WaLabel::where('branch_id', $device->branch_id)->orderBy('name')->get(['id', 'name', 'color'])
            ->map(fn ($label) => [...$label->only('id', 'name', 'color'), 'assigned' => in_array($label->id, $assigned, true)])
            ->all();
    }

    private function notes(WaChat $chat): array
    {
        return $chat->notes()->with('user:id,name')->latest()->get()
            ->map(fn ($note) => ['id' => $note->id, 'note' => $note->note, 'author' => $note->user?->name, 'created_at' => $note->created_at->toIso8601String()])
            ->all();
    }

    private function contact(WaDevice $device, WaChat $chat): ?array
    {
        if ($chat->is_group || ! $chat->phone) {
            return null;
        }
        $customer = Customer::where('branch_id', $device->branch_id)->where('phone', $chat->phone)->with('groups:id,name')->first();

        return $customer ? $this->contactPayload($customer) : null;
    }

    private function contactPayload(Customer $customer): array
    {
        return ['id' => $customer->id, 'name' => $customer->displayName(), 'groups' => $customer->groups()->pluck('name')];
    }
}
