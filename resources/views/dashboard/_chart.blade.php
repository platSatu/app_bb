{{-- Grafik batang mingguan (ApexCharts). $chart = ['labels' => [...], 'series' => [['name', 'data']]], $money = format rupiah. --}}
<div id="{{ $id }}" style="min-height: 280px"></div>

@once
    @push('scripts')
        <script src="{{ asset('be') }}/assets/libs/apexcharts/apexcharts.min.js"></script>
    @endpush
@endonce

@push('scripts')
    <script>
        (function () {
            var money = @json($money ?? false);
            var short = function (v) {
                if (!money) return Math.round(v).toLocaleString('id-ID');
                if (v >= 1e9) return 'Rp ' + (v / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
                if (v >= 1e6) return 'Rp ' + (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
                if (v >= 1e3) return 'Rp ' + (v / 1e3).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb';
                return 'Rp ' + v;
            };
            new ApexCharts(document.getElementById(@json($id)), {
                chart: { type: 'bar', height: 280, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
                series: @json($chart['series']),
                xaxis: { categories: @json($chart['labels']), labels: { style: { fontSize: '11px' } } },
                yaxis: { labels: { formatter: short } },
                plotOptions: { bar: { columnWidth: '45%', borderRadius: 4 } },
                dataLabels: { enabled: false },
                legend: { position: 'top', horizontalAlign: 'left' },
                grid: { borderColor: 'rgba(0,0,0,.06)' },
                tooltip: {
                    x: { formatter: function (label) { return 'Minggu mulai ' + label; } },
                    y: { formatter: function (v) { return money ? 'Rp ' + Math.round(v).toLocaleString('id-ID') : Math.round(v).toLocaleString('id-ID'); } }
                },
                colors: ['#e5534b', '#f0a33a', '#2fb380'],
            }).render();
        })();
    </script>
@endpush
