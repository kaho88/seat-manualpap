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

                        <div class="form-group mb-3">
                            <label for="date">{{ trans('manualpap::manualpap.bulk_date') }}</label>
                            <input type="date" name="date" id="date"
                                   class="form-control"
                                   value="{{ old('date', date('Y-m-d')) }}"
                                   required>
                            <small class="text-muted">
                                {{ trans('manualpap::manualpap.bulk_date_hint') }}
                            </small>
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
    var dateInput = document.getElementById('date');
    var autoName  = document.getElementById('auto_op_name');

    var monthNames = {
        '01': 'Januar', '02': 'Februar', '03': 'März',
        '04': 'April',  '05': 'Mai',     '06': 'Juni',
        '07': 'Juli',   '08': 'August',  '09': 'September',
        '10': 'Oktober','11': 'November','12': 'Dezember'
    };

    function updateAutoName() {
        var val = dateInput.value;
        if (val) {
            var parts = val.split('-');
            var month = monthNames[parts[1]] || parts[1];
            autoName.value = 'Allianz FAT ' + month + ' ' + parts[0];
        } else {
            autoName.value = '';
        }
    }

    dateInput.addEventListener('change', updateAutoName);
    updateAutoName();
});
</script>
@endsection
