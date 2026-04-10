@extends('web::layouts.app')

@section('title', trans('manualpap::seat.bulk_title'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="mb-0">{{ trans('manualpap::seat.bulk_title') }}</h3>
                </div>

                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('skipped_names') && count(session('skipped_names')) > 0)
                        <div class="alert alert-info">
                            <strong>{{ trans('manualpap::manualpap.bulk_skipped_names') }}</strong>
                            <ul class="mb-0">
                                @foreach(session('skipped_names') as $name)
                                    <li>{{ $name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(session('failed_names') && count(session('failed_names')) > 0)
                        <div class="alert alert-warning">
                            <strong>{{ trans('manualpap::manualpap.bulk_failed_names') }}</strong>
                            <ul class="mb-0">
                                @foreach(session('failed_names') as $name)
                                    <li>{{ $name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="alert alert-info">
                        <strong>{{ trans('manualpap::manualpap.bulk_info_title') }}</strong><br>
                        {{ trans('manualpap::manualpap.bulk_info_text') }}
                    </div>

                    <form method="POST" action="{{ route('manualpap.bulkStore') }}">
                        @csrf

                        <div class="form-row mb-3">
                            <div class="form-group col-md-6">
                                <label for="month">{{ trans('manualpap::manualpap.bulk_month') }}</label>
                                <select name="month" id="month" class="form-control" required>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" {{ (int)old('month', now()->month) == $m ? 'selected' : '' }}>
                                            {{ \Carbon\Carbon::create()->month($m)->isoFormat('MMMM') }}
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="year">{{ trans('manualpap::manualpap.bulk_year') }}</label>
                                <select name="year" id="year" class="form-control" required>
                                    @for($y = now()->year; $y >= 2020; $y--)
                                        <option value="{{ $y }}" {{ (int)old('year', now()->year) == $y ? 'selected' : '' }}>
                                            {{ $y }}
                                        </option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label>{{ trans('manualpap::manualpap.bulk_auto_name') }}</label>
                            <input type="text" class="form-control" disabled
                                   id="auto_op_name" value="">
                            <small class="text-muted">
                                {{ trans('manualpap::manualpap.bulk_auto_name_hint') }}
                            </small>
                        </div>

                        <div class="form-group mb-3">
                            <label>{{ trans('manualpap::manualpap.bulk_auto_tag') }}</label>
                            <input type="text" class="form-control" disabled
                                   value="Allypap">
                        </div>

                        <div class="form-group mb-3">
                            <label for="character_list">{{ trans('manualpap::manualpap.bulk_list_label') }}</label>
                            <textarea name="character_list" id="character_list"
                                      class="form-control"
                                      rows="15"
                                      placeholder="{{ trans('manualpap::manualpap.bulk_list_placeholder') }}"
                                      required>{{ old('character_list') }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            {{ trans('manualpap::manualpap.bulk_submit') }}
                        </button>

                        <a href="{{ route('manualpap.index') }}" class="btn btn-secondary ml-2">
                            {{ trans('manualpap::manualpap.back_to_single') }}
                        </a>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var monthSelect = document.getElementById('month');
    var yearSelect  = document.getElementById('year');
    var autoName    = document.getElementById('auto_op_name');

    var monthNames = {
        1: 'Januar', 2: 'Februar', 3: 'März',
        4: 'April',  5: 'Mai',     6: 'Juni',
        7: 'Juli',   8: 'August',  9: 'September',
        10: 'Oktober', 11: 'November', 12: 'Dezember'
    };

    function updateAutoName() {
        var month = monthSelect.value;
        var year  = yearSelect.value;
        if (month && year) {
            autoName.value = 'Allianz FAT ' + monthNames[month] + ' ' + year;
        }
    }

    monthSelect.addEventListener('change', updateAutoName);
    yearSelect.addEventListener('change', updateAutoName);
    updateAutoName();
});
</script>
@endsection
