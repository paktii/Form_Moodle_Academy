<x-portal-layout title="คำร้องแบบฟอร์มขอรับบริการ" header-context="รายงานสถิติคำร้อง" tab-title="Request Reports" role="officer" :show-header-logo="true">
    <main class="portal-main report-page">
        <div class="report-page__heading">
            <div>
                <h2>รายงานสถิติคำร้อง</h2>
                <p>แสดง{{ $reportSubject }} โดยนับจากวันที่ยื่นคำร้อง</p>
            </div>
            <x-portal-button :href="route('officer.reviews')" variant="secondary">กลับหน้ารายการคำร้อง</x-portal-button>
        </div>

        <form class="portal-card report-filter" method="get" action="{{ route('officer.reports') }}" data-report-filter>
            <label class="report-filter__field">
                <span>ปี</span>
                <select name="year" class="filter-control">
                    @foreach($availableYears as $year)
                    <option value="{{ $year }}" @selected($year === $selectedYear)>พ.ศ. {{ $year + 543 }}</option>
                    @endforeach
                </select>
            </label>
            <label class="report-filter__field">
                <span>เดือน</span>
                <select name="month" class="filter-control">
                    @foreach($monthNames as $month => $monthName)
                    <option value="{{ $month }}" @selected($month === $selectedMonth)>{{ $monthName }}</option>
                    @endforeach
                </select>
            </label>
            <label class="report-filter__field">
                <span>ประเภทแบบฟอร์ม</span>
                <select name="form_type" class="filter-control">
                    <option value="">ทุกแบบฟอร์ม</option>
                    @foreach($formTypes as $formType)
                    <option value="{{ $formType->form_type_code }}" @selected($formType->form_type_code === $selectedFormType)>{{ $formType->form_type_code }} — {{ $formType->short_name_th }}</option>
                    @endforeach
                </select>
            </label>
            <label class="report-filter__field">
                <span>ผลการพิจารณา</span>
                <select name="decision" class="filter-control">
                    @foreach($decisionOptions as $decision => $label)
                    <option value="{{ $decision }}" @selected($decision === $selectedDecision)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <x-portal-button :href="route('officer.reports')" variant="secondary" class="report-filter__clear" aria-label="ล้างตัวกรอง" title="ล้างตัวกรอง"><x-portal-icon name="filter-x" /><span class="report-filter__clear-label">ล้างตัวกรอง</span></x-portal-button>
        </form>

        <section class="report-summary" aria-label="สรุปจำนวนคำร้อง">
            <article class="portal-card report-summary__card">
                <span>{{ $reportSubject }}ในปี {{ $selectedYearDisplay }}</span>
                <strong>{{ number_format($yearTotal) }}</strong>
                <small>คำร้อง</small>
            </article>
            <article class="portal-card report-summary__card">
                <span>{{ $reportSubject }}เดือน{{ $selectedMonthName }} {{ $selectedYearDisplay }}</span>
                <strong>{{ number_format($monthTotal) }}</strong>
                <small>คำร้อง</small>
            </article>
            <article class="portal-card report-summary__card">
                <span>สัดส่วนเดือน{{ $selectedMonthName }}เทียบกับทั้งปี</span>
                <strong>{{ rtrim(rtrim(number_format($monthShare, 1), '0'), '.') }}%</strong>
                <small>ของ{{ $reportSubject }}ในปี {{ $selectedYearDisplay }}</small>
            </article>
        </section>

        @if($selectedDecision === 'all')
        <section class="portal-card report-chart-card" aria-labelledby="decision-chart-heading">
            <div class="report-chart-card__heading">
                <div>
                    <h3 id="decision-chart-heading">สัดส่วนผลการพิจารณาที่สิ้นสุดแล้ว</h3>
                    <p>แยกจำนวนคำร้องตามผลการพิจารณาทั้งปีและเดือนที่เลือก</p>
                </div>
            </div>
            <div class="report-donuts">
                @foreach([
                    ['key' => 'year_count', 'title' => 'ปี '.$selectedYearDisplay, 'total' => $decisionBreakdownRows->sum('year_count')],
                    ['key' => 'month_count', 'title' => $selectedMonthName.' '.$selectedYearDisplay, 'total' => $decisionBreakdownRows->sum('month_count')],
                ] as $chart)
                @php($offset = 0)
                <article class="report-donut">
                    <h4>{{ $chart['title'] }}</h4>
                    <div class="report-donut__content">
                        <div class="report-donut__visual">
                            <svg viewBox="0 0 42 42" role="img" aria-label="ผลการพิจารณาที่สิ้นสุดแล้ว {{ $chart['title'] }} รวม {{ $chart['total'] }} คำร้อง">
                                <circle class="report-donut__background" cx="21" cy="21" r="15.9155" pathLength="100" fill="none" stroke-width="6" />
                                @if($chart['total'] > 0)
                                @foreach($decisionBreakdownRows as $row)
                                @php($percentage = ($row[$chart['key']] / $chart['total']) * 100)
                                @if($percentage > 0)
                                <circle cx="21" cy="21" r="15.9155" pathLength="100" fill="none" stroke="{{ $row['color'] }}" stroke-width="6" stroke-linecap="butt" stroke-dasharray="{{ round($percentage, 4) }} {{ round(100 - $percentage, 4) }}" stroke-dashoffset="-{{ round($offset, 4) }}" transform="rotate(-90 21 21)" />
                                @endif
                                @php($offset += $percentage)
                                @endforeach
                                @endif
                            </svg>
                            <div class="report-donut__total"><strong>{{ number_format($chart['total']) }}</strong><span>คำร้อง</span></div>
                        </div>
                        <ul class="report-donut__legend">
                            @foreach($decisionBreakdownRows as $row)
                            <li><i style="background-color: {{ $row['color'] }}"></i><span><strong>{{ $row['label'] }}</strong></span><b>{{ number_format($row[$chart['key']]) }}</b></li>
                            @endforeach
                        </ul>
                    </div>
                </article>
                @endforeach
            </div>
        </section>
        @endif

        <section class="portal-card report-chart-card" aria-labelledby="report-chart-heading">
            <div class="report-chart-card__heading">
                <div>
                    <h3 id="report-chart-heading">สัดส่วน{{ $reportSubject }}แยกตามแบบฟอร์ม</h3>
                    <p>เปรียบเทียบสัดส่วนทั้งปีและเดือนที่เลือก</p>
                </div>
            </div>
            <div class="report-donuts">
                @foreach([
                    ['key' => 'year_count', 'title' => 'ปี '.$selectedYearDisplay, 'total' => $yearTotal],
                    ['key' => 'month_count', 'title' => $selectedMonthName.' '.$selectedYearDisplay, 'total' => $monthTotal],
                ] as $chart)
                @php($offset = 0)
                <article class="report-donut">
                    <h4>{{ $chart['title'] }}</h4>
                    <div class="report-donut__content">
                        <div class="report-donut__visual">
                            <svg viewBox="0 0 42 42" role="img" aria-label="{{ $reportSubject }}{{ $chart['title'] }} รวม {{ $chart['total'] }} คำร้อง">
                                <circle class="report-donut__background" cx="21" cy="21" r="15.9155" pathLength="100" fill="none" stroke-width="6" />
                                @if($chart['total'] > 0)
                                @foreach($reportRows as $row)
                                @php($percentage = ($row[$chart['key']] / $chart['total']) * 100)
                                @if($percentage > 0)
                                <circle cx="21" cy="21" r="15.9155" pathLength="100" fill="none" stroke="{{ $row['color'] }}" stroke-width="6" stroke-linecap="butt" stroke-dasharray="{{ round($percentage, 4) }} {{ round(100 - $percentage, 4) }}" stroke-dashoffset="-{{ round($offset, 4) }}" transform="rotate(-90 21 21)" />
                                @endif
                                @php($offset += $percentage)
                                @endforeach
                                @endif
                            </svg>
                            <div class="report-donut__total"><strong>{{ number_format($chart['total']) }}</strong><span>คำร้อง</span></div>
                        </div>
                        <ul class="report-donut__legend">
                            @foreach($reportRows as $row)
                            <li><i style="background-color: {{ $row['color'] }}"></i><span><strong>{{ $row['code'] }}</strong><small>{{ $row['name'] }}</small></span><b>{{ number_format($row[$chart['key']]) }}</b></li>
                            @endforeach
                        </ul>
                    </div>
                </article>
                @endforeach
            </div>
        </section>

        <section class="portal-card report-table-card" aria-labelledby="report-table-heading">
            <div class="report-table-card__heading">
                <div>
                    <h3 id="report-table-heading">จำนวน{{ $reportSubject }}แยกตามแบบฟอร์ม</h3>
                    <p>เปรียบเทียบยอดทั้งปีและเดือนที่เลือก</p>
                </div>
            </div>
            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th scope="col">ประเภทฟอร์ม</th>
                            <th scope="col">ชื่อแบบฟอร์ม</th>
                            <th scope="col" class="report-table__number-heading">ปี {{ $selectedYearDisplay }}</th>
                            <th scope="col" class="report-table__number-heading">{{ $selectedMonthName }} {{ $selectedYearDisplay }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportRows as $row)
                        <tr>
                            <th scope="row">{{ $row['code'] }}</th>
                            <td class="report-table__name">{{ $row['name'] }}</td>
                            <td class="report-table__number" data-label="ปี {{ $selectedYearDisplay }}">{{ number_format($row['year_count']) }}</td>
                            <td class="report-table__number" data-label="{{ $selectedMonthName }} {{ $selectedYearDisplay }}">{{ number_format($row['month_count']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="report-table__empty">ไม่พบประเภทแบบฟอร์ม</td></tr>
                        @endforelse
                    </tbody>
                    @if($reportRows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="2" scope="row">รวมทั้งหมด</th>
                            <td class="report-table__number" data-label="ปี {{ $selectedYearDisplay }}">{{ number_format($yearTotal) }}</td>
                            <td class="report-table__number" data-label="{{ $selectedMonthName }} {{ $selectedYearDisplay }}">{{ number_format($monthTotal) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </section>
    </main>
</x-portal-layout>
