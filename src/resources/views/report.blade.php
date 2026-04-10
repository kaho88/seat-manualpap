@extends('web::layouts.app')

@section('title', trans('manualpap::seat.report_title'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="mb-0">{{ trans('manualpap::seat.report_title') }}</h3>
                </div>

                <div class="card-body">

                    {{-- Filter --}}
                    <form method="GET" action="{{ route('manualpap.report') }}" class="form-inline mb-3">
                        <div class="form-group mr-2">
                            <label for="month" class="mr-1">{{ trans('manualpap::manualpap.report_month') }}</label>
                            <select name="month" id="month" class="form-control">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::create()->month($m)->isoFormat('MMMM') }}
                                    </option>
                                @endfor
                            </select>
                        </div>

                        <div class="form-group mr-2">
                            <label for="year" class="mr-1">{{ trans('manualpap::manualpap.report_year') }}</label>
                            <select name="year" id="year" class="form-control">
                                @foreach($availableMonths as $am)
                                    @if(!$loop->first && $am->year != $loop->item->year)
                                        {{-- grouped by year --}}
                                    @endif
                                    <option value="{{ $am->year }}" {{ $am->year == $year ? 'selected' : '' }}>
                                        {{ $am->year }}
                                    </option>
                                @endforeach
                                @if($availableMonths->where('year', now()->year)->isEmpty())
                                    <option value="{{ now()->year }}" {{ now()->year == $year ? 'selected' : '' }}>
                                        {{ now()->year }}
                                    </option>
                                @endif
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            {{ trans('manualpap::manualpap.report_filter') }}
                        </button>
                    </form>

                    {{-- API hint --}}
                    <div class="alert alert-info small">
                        {{ trans('manualpap::manualpap.report_api_hint') }}:
                        <code>GET {{ url('api/manual-pap/report/' . $year . '/' . $month) }}</code>
                        &nbsp; X-Token: YOUR_SEAT_API_TOKEN
                    </div>

                    {{-- Results table --}}
                    @if(count($results) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ trans('manualpap::manualpap.report_character') }}</th>
                                        <th>{{ trans('manualpap::manualpap.report_char_id') }}</th>
                                        <th>{{ trans('manualpap::manualpap.report_total_paps') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($results as $i => $row)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $row['character_name'] }}</td>
                                            <td>{{ $row['character_id'] }}</td>
                                            <td><strong>{{ $row['total_paps'] }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <td colspan="3"><strong>{{ trans('manualpap::manualpap.report_total') }}</strong></td>
                                        <td><strong>{{ array_sum(array_column($results, 'total_paps')) }}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <p class="text-muted small">
                            {{ trans('manualpap::manualpap.report_unique_chars') }}: {{ count($results) }}
                        </p>
                    @else()
                        <div class="alert alert-warning">
                            {{ trans('manualpap::manualpap.report_no_data') }}
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>
</div>
@endsection
