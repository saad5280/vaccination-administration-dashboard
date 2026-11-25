<div>
    <div class="page-heading">
        <div>
            <p class="eyebrow">ACCESS CONTROL</p>
            <h1>Roles & permissions</h1>
            <p class="muted">Review the access granted to each staff role.</p>
        </div>
        <a class="button primary" href="{{ route('roles.create') }}" wire:navigate>＋ Create role</a>
    </div>

    <div class="role-grid">
        @foreach ($roles as $role)
            <article class="role-card">
                <div class="role-card-top">
                    <div>
                        <span class="role-symbol">◈</span>
                        <h2>{{ $role->label }}</h2>
                        <p>{{ $role->users_count }} {{ Illuminate\Support\Str::plural('user', $role->users_count) }} assigned</p>
                    </div>
                    <div class="role-actions">
                        @if ($role->name !== 'administrator')
                            <a class="icon-button" href="{{ route('roles.edit', $role) }}" aria-label="Edit {{ $role->label }} permissions">↗</a>
                        @endif
                        @if (! in_array($role->name, ['administrator', 'vaccinator', 'registration', 'inventory-officer', 'manager'], true))
                            <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')">
                                @csrf
                                @method('DELETE')
                                <button class="icon-button danger-link" type="submit" aria-label="Delete {{ $role->label }} role">×</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="permission-list">
                    @foreach ($role->permissions as $permission)
                        <span class="permission-chip">{{ $permission->label }}</span>
                    @endforeach
                </div>
                @if (in_array($role->name, ['administrator', 'vaccinator', 'registration', 'inventory-officer', 'manager'], true))
                    <div class="protected-note">Built-in role</div>
                @endif
            </article>
        @endforeach
    </div>
</div>
