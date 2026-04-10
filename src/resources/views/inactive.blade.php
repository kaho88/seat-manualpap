@extends('web::layouts.app')

@section('title', trans('manualpap::seat.inactive_title'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="mb-0">{{ trans('manualpap::seat.inactive_title') }}</h3>
                </div>

                <div class="card-body">

                    {{-- Info box --}}
                    <div class="alert alert-info">
                        <strong>{{ trans('manualpap::manualpap.inactive_info_title') }}</strong><br>
                        {{ trans('manualpap::manualpap.inactive_info_text', [
                            'month1' => Carbon\Carbon::now()->subMonthsNoOverflow(1)->isoFormat('MMMM Y'),
                            'month2' => Carbon\Carbon::now()->subMonthsNoOverflow(2)->isoFormat('MMMM Y'),
                            'month3' => Carbon\Carbon::now()->subMonthsNoOverflow(3)->isoFormat('MMMM Y'),
                        ]) }}
                    </div>

                    @if(empty($corporationIds))
                        <div class="alert alert-warning">
                            {{ trans('manualpap::manualpap.inactive_no_corps') }}
                        </div>
                    @else()
                        <div class="alert alert-secondary small">
                            {{ trans('manualpap::manualpap.inactive_corp_list') }}:
                            <code>{{ implode(', ', $corporationIds) }}</code>
                        </div>

                        {{-- API hint --}}
                        <div class="alert alert-info small">
                            {{ trans('manualpap::manualpap.report_api_hint') }}:
                            <code>GET {{ url('api/manual-pap/inactive') }}</code>
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
                                            <th>{{ trans('manualpap::manualpap.inactive_corporation') }}</th>
                                            <th>{{ trans('manualpap::manualpap.inactive_alliance') }}</th>
                                            <th>{{ trans('manualpap::manualpap.inactive_token') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($results as $i => $row)
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>{{ $row['character_name'] }}</td>
                                                <td>{{ $row['corporation_name'] }}</td>
                                                <td>{{ $row['alliance_name'] ?? '-' }}</td>
                                                <td>
                                                    @if($row['has_token'])
                                                        <span style="color: #28a745; font-weight: bold;">&#10003;</span>
                                                    @else
                                                        <span style="color: #dc3545; font-weight: bold;">&#10007;</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-active">
                                            <td colspan="4"><strong>{{ trans('manualpap::manualpap.inactive_total') }}</strong></td>
                                            <td><strong>{{ count($results) }}</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else()
                            <div class="alert alert-success">
                                {{ trans('manualpap::manualpap.inactive_none') }}
                            </div>
                        @endif
                    @endif

                </div>
            </div>

        </div>
    </div>
</div>
@endsection
