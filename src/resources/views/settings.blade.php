@extends('web::layouts.app')

@section('title', trans('manualpap::seat.settings_title'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="mb-0">{{ trans('manualpap::seat.settings_title') }}</h3>
                </div>

                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
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
                        {{ trans('manualpap::manualpap.settings_info') }}
                    </div>

                    {{-- Add form --}}
                    <form method="POST" action="{{ route('manualpap.settings.add') }}" class="form-inline mb-4">
                        @csrf
                        <div class="form-group mr-2" style="flex: 1;">
                            <input type="text" name="corporation_name" id="corporation_name"
                                   class="form-control" style="width: 100%;"
                                   placeholder="{{ trans('manualpap::manualpap.settings_placeholder') }}"
                                   value="{{ old('corporation_name') }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            {{ trans('manualpap::manualpap.settings_add') }}
                        </button>
                    </form>

                    {{-- Current list --}}
                    @if(count($corporations) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ trans('manualpap::manualpap.settings_corp_name') }}</th>
                                        <th>{{ trans('manualpap::manualpap.settings_corp_id') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($corporations as $corp)
                                        <tr>
                                            <td>{{ $corpNames[$corp->corporation_id] ?? ('Unknown #' . $corp->corporation_id) }}</td>
                                            <td>{{ $corp->corporation_id }}</td>
                                            <td class="text-right">
                                                <form method="POST" action="{{ route('manualpap.settings.remove', $corp->corporation_id) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                            onclick="return confirm('{{ trans('manualpap::manualpap.settings_confirm_remove') }}')">
                                                        {{ trans('manualpap::manualpap.settings_remove') }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else()
                        <div class="alert alert-warning">
                            {{ trans('manualpap::manualpap.settings_no_corps') }}
                        </div>
                    @endif

                </div>
            </div>

            {{-- Blocklist --}}
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="mb-0">{{ trans('manualpap::manualpap.blocklist_title') }}</h3>
                </div>

                <div class="card-body">

                    <div class="alert alert-info">
                        {{ trans('manualpap::manualpap.blocklist_info') }}
                    </div>

                    {{-- Add form --}}
                    <form method="POST" action="{{ route('manualpap.blocklist.add') }}" class="form-inline mb-4">
                        @csrf
                        <div class="form-group mr-2" style="flex: 1;">
                            <input type="text" name="character_name" id="character_name"
                                   class="form-control" style="width: 100%;"
                                   placeholder="{{ trans('manualpap::manualpap.blocklist_placeholder') }}"
                                   value="{{ old('character_name') }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            {{ trans('manualpap::manualpap.blocklist_add') }}
                        </button>
                    </form>

                    {{-- Current list --}}
                    @if(count($blocklist) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ trans('manualpap::manualpap.blocklist_char_name') }}</th>
                                        <th>{{ trans('manualpap::manualpap.blocklist_char_id') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($blocklist as $char)
                                        <tr>
                                            <td>{{ $blockCharNames[$char->character_id] ?? ('Unknown #' . $char->character_id) }}</td>
                                            <td>{{ $char->character_id }}</td>
                                            <td class="text-right">
                                                <form method="POST" action="{{ route('manualpap.blocklist.remove', $char->character_id) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                            onclick="return confirm('{{ trans('manualpap::manualpap.blocklist_confirm_remove') }}')">
                                                        {{ trans('manualpap::manualpap.blocklist_remove') }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            {{ trans('manualpap::manualpap.blocklist_empty') }}
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>
</div>
@endsection
