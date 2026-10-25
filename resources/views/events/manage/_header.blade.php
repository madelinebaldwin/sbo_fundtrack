<div class="event-management-heading">
    <h2>{{ $event->name }}</h2>
</div>
<nav class="event-management-tabs" aria-label="Event management">
    @foreach ([
        ['Overview', 'events.manage.overview', 'overview'],
        ['Collections', 'events.manage.collections', 'collections'],
        ['Expenses', 'events.manage.expenses', 'expenses'],
        ['Reports', 'events.manage.reports', 'reports'],
        ['Analytics', 'events.manage.analytics', 'analytics'],
        ['History', 'events.manage.history', 'history'],
    ] as [$label, $routeName, $tab])
        <a href="{{ route($routeName, $event) }}" class="event-management-tab {{ $activeTab === $tab ? 'is-active' : '' }}" @if ($activeTab === $tab) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
