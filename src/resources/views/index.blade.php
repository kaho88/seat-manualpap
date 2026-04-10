@extends('web::layouts.app')

@section('title', trans('manualpap::seat.plugin_name'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="mb-0">{{ trans('manualpap::seat.manual_pap') }}</h3>
                </div>

                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
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

                    <form method="POST" action="{{ route('manualpap.store') }}">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="operation_id">{{ trans('manualpap::manualpap.operation') }}</label>
                            <select name="operation_id" id="operation_id" class="form-control" required>
                                <option value="">{{ trans('manualpap::manualpap.select_operation') }}</option>
                                @foreach($operations as $operation)
                                    <option value="{{ $operation->id }}"
                                        {{ old('operation_id') == $operation->id ? 'selected' : '' }}>
                                        #{{ $operation->id }}
                                        - {{ $operation->title }}
                                        @if($operation->start_at)
                                            ({{ $operation->start_at }})
                                        @endif
                                        @if($operation->is_cancelled)
                                            [{{ trans('manualpap::manualpap.cancelled') }}]
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="character_id">{{ trans('manualpap::manualpap.character_id') }}</label>
                            <input type="number" name="character_id" id="character_id"
                                   class="form-control"
                                   value="{{ old('character_id') }}"
                                   placeholder="e.g. 2119234394"
                                   required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="ship_type_id">{{ trans('manualpap::manualpap.ship_type_id') }}</label>
                            <input type="number" name="ship_type_id" id="ship_type_id"
                                   class="form-control"
                                   value="{{ old('ship_type_id') }}"
                                   placeholder="{{ trans('manualpap::manualpap.optional') }}">
                        </div>

                        <div class="form-group mb-3">
                            <label for="value">{{ trans('manualpap::manualpap.value') }}</label>
                            <input type="number" name="value" id="value"
                                   class="form-control"
                                   value="{{ old('value') }}"
                                   min="0"
                                   placeholder="{{ trans('manualpap::manualpap.value_hint') }}">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            {{ trans('manualpap::manualpap.submit') }}
                        </button>
                    </form>

                </div>
            </div>

            {{-- API Usage Info --}}
            <div class="card mt-3">
                <div class="card-header">
                    <h4 class="mb-0">{{ trans('manualpap::manualpap.api_title') }}</h4>
                </div>
                <div class="card-body">
                    <p>{{ trans('manualpap::manualpap.api_description') }}</p>

                    <pre><code>POST {{ url('api/manual-pap') }}
X-Token: YOUR_SEAT_API_TOKEN
Content-Type: application/json

{
    "operation_id": 1,
    "character_id": 2119234394,
    "ship_type_id": 643,
    "value": 1
}</code></pre>

                    <p class="mt-2 text-muted small">
                        {{ trans('manualpap::manualpap.api_env_hint') }}
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
