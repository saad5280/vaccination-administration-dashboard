@if ($demoAccounts !== [])
    <section class="demo-access" aria-labelledby="demo-access-heading">
        <div>
            <h3 id="demo-access-heading">Explore with a demo role</h3>
            <p>These accounts use fictional sample data. Changes may be visible to other visitors.</p>
        </div>

        <div class="demo-account-list">
            @foreach ($demoAccounts as $demoAccount)
                <article class="demo-account">
                    <h4>{{ $demoAccount['label'] }}</h4>
                    <dl class="demo-credentials">
                        <div>
                            <dt>Email</dt>
                            <dd>{{ $demoAccount['email'] }}</dd>
                        </div>
                        <div>
                            <dt>Password</dt>
                            <dd>{{ $demoAccount['password'] }}</dd>
                        </div>
                    </dl>
                    <form method="POST" action="{{ route('login.store') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $demoAccount['email'] }}">
                        <input type="hidden" name="password" value="{{ $demoAccount['password'] }}">
                        <button class="button primary full" type="submit">Sign in as {{ $demoAccount['label'] }} <span>→</span></button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>
@endif
