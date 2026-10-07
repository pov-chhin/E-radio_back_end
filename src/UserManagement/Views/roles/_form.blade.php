@csrf

<div class="form-grid" style="margin-bottom:24px">
    <label>{{ __('Name') }}
        <input id="name" name="name" type="text" value="{{ old('name', $role->name ?? '') }}" required>
        @error('name') <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Slug') }}
        <input id="slug" name="slug" type="text" value="{{ old('slug', $role->slug ?? '') }}" required>
        @error('slug') <em>{{ $message }}</em> @enderror
    </label>
</div>

@php
    $checkedIds = collect(old('permissions', isset($role) ? $role->permissions->pluck('id')->all() : []));
    $matrixRows = collect($permissionMatrix)->filter(fn ($row) => collect($row['cells'])->filter()->isNotEmpty());
@endphp

<div style="margin-bottom:24px">
    <label style="display:block;font-size:9px;font-weight:700;margin-bottom:10px">{{ __('Permissions') }}</label>

    @if($matrixRows->isNotEmpty())
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Resource') }}</th>
                        @foreach($actions as $actionLabel)
                            <th style="text-align:center">{{ __($actionLabel) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($matrixRows as $row)
                        <tr>
                            <td><b>{{ __($row['label']) }}</b></td>
                            @foreach($actions as $actionLabel)
                                <td style="text-align:center">
                                    @if($permission = $row['cells'][$actionLabel] ?? null)
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($checkedIds->contains($permission->id))>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($otherPermissions->isNotEmpty())
        <label style="display:block;font-size:9px;font-weight:700;margin:20px 0 10px">{{ __('Other') }}</label>
        <div class="checkbox-grid">
            @foreach($otherPermissions as $permission)
                <label>
                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($checkedIds->contains($permission->id))>
                    {{ __($permission->name) }}
                </label>
            @endforeach
        </div>
    @endif

    @if($matrixRows->isEmpty() && $otherPermissions->isEmpty())
        <p class="muted">{!! __('No permissions yet — <a href=":url">create one</a>.', ['url' => route('admin.permissions.create')]) !!}</p>
    @endif
</div>

<button type="submit" class="primary-button">{{ __('Save') }}</button>
