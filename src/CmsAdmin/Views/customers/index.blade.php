@extends('cms-admin::layout')

@section('title', 'Customers')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Access control</p>
            <h1>Customers</h1>
            <p>Accounts registered through the app.</p>
        </div>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th class="right">Bookmarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                    <tr>
                        <td>
                            <div class="cust-cell">
                                {{-- The initial sits underneath, so a customer without a photo (or one that fails to load) still gets an avatar. --}}
                                <span class="cust-avatar">{{ mb_strtoupper(mb_substr(trim($customer->name ?: $customer->email), 0, 1)) ?: '?' }}@if($customer->avatar_url)<img src="{{ $customer->avatar_url }}" alt="" loading="lazy" onerror="this.remove()">@endif</span>
                                <b>{{ $customer->name ?: '—' }}</b>
                            </div>
                        </td>
                        <td class="muted">{{ $customer->username ?? '—' }}</td>
                        <td class="muted">{{ $customer->email }}</td>
                        <td class="muted">{{ $customer->created_at->format('M j, Y') }}</td>
                        <td class="right">{{ $customer->bookmarks_count }}</td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="5">No customers yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>
@endsection
