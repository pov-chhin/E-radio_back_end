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
                        <td><b>{{ $customer->name ?: '—' }}</b></td>
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
