@extends('beautymail::templates.sunny')

@section('content')

  @include ('beautymail::templates.sunny.heading' , [
      'heading' => ($is_test ? '[TEST] ' : '') . 'LMS Status Changed',
      'level' => 'h1',
  ])

  @include('beautymail::templates.sunny.contentStart')

  @php
    $labels = ['up' => 'Up', 'down' => 'Down', 'auth_error' => 'Auth error'];
    $colors = ['up' => '#1e7e34', 'down' => '#c82333', 'auth_error' => '#b8860b'];
  @endphp

  @if ($is_test)
    <p style="background: #fff3cd; padding: 8px;">
      <strong>This is a test</strong> sent with <code>php artisan check:lmsStatus --test-email</code>.
      It shows every school that isn't up right now as if its status had just changed.
    </p>
  @endif

  @if (count($changes))
    <p>The latest LMS check found {{ count($changes) }} status {{ count($changes) === 1 ? 'change' : 'changes' }}:</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 14px;">
      <tr style="background: #f2f2f2; text-align: left;">
        <th>School</th>
        <th>Was</th>
        <th>Now</th>
        <th>Details</th>
      </tr>
      @foreach ($changes as $change)
        <tr style="border-top: 1px solid #ddd;">
          <td>
            <strong>{{ $change['name'] }}</strong><br>
            <span style="color: #666;">{{ $change['lms'] }}{{ $change['base_url'] ? ' · ' . $change['base_url'] : '' }}</span>
          </td>
          <td>{{ $labels[$change['from']] ?? $change['from'] }}</td>
          <td style="color: {{ $colors[$change['status']] ?? '#000' }};"><strong>{{ $labels[$change['status']] ?? $change['status'] }}</strong></td>
          <td>
            @if ($change['status'] === 'up')
              Responding{{ $change['response_ms'] !== null ? ' in ' . $change['response_ms'] . ' ms' : '' }}
            @else
              {{ $change['error'] }}
            @endif
          </td>
        </tr>
      @endforeach
    </table>
  @else
    <p>Every school is up right now, so there's nothing to report.</p>
  @endif

  <p style="margin-top: 16px;">
    All schools right now: <strong>{{ $counts['up'] }}</strong> up,
    <strong>{{ $counts['down'] }}</strong> down,
    <strong>{{ $counts['auth_error'] }}</strong> with auth errors
    (of {{ $counts['total'] }}).
    @foreach ($counts['by_lms'] as $lms => $lmsCounts)
      <br>{{ $lms }}: {{ $lmsCounts['up'] }} of {{ $lmsCounts['total'] }} up
    @endforeach
  </p>

  <p>
    <strong>Down</strong> means the LMS didn't respond (or returned a server error), even after a retry.
    <strong>Auth error</strong> means the LMS responded but rejected ADAPT's LTI key, so that school's ADAPT
    integration (grade passback, etc.) is broken until the key or registration is fixed on their side.
  </p>
  @if ($counts['total'] > 0 && $counts['down'] >= max(2, $counts['total'] / 2))
    <p>
      <strong>Most schools are down.</strong> If this continues, consider turning off LMS connections
      under <strong>LTI Integrations &rarr; Outages</strong>.
    </p>
  @endif

  <p><a href="{{ $dashboard_url }}">Open the LMS status dashboard</a> (LTI Integrations &rarr; LMS Status).</p>
  <p><strong>This is an automatically generated email.</strong></p>
  @include('beautymail::templates.sunny.contentEnd')

@stop
