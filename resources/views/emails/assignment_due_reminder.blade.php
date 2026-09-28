@extends('beautymail::templates.sunny')

@section('content')

  @include ('beautymail::templates.sunny.heading' , [
  'heading' => 'Upcoming Assignment Due',
  'level' => 'h1',
  ])

  @include('beautymail::templates.sunny.contentStart')

  @php
    $hours = (int) $hours_until_due;
      if ($hours === 168) {
          $time_until_due = '1 week';
      } elseif ($hours === 24) {
          $time_until_due = '1 day';
      } elseif ($hours === 1) {
          $time_until_due = '1 hour';
      } else {
          $time_until_due = $hours . ' hours';
      }
  @endphp

  <p>Hi {{ $student_first_name }},</p>

  <p>
    Just a friendly reminder that the assignment <strong>{{ $assignment }}</strong> from the course
    <strong>{{ $course }}</strong> is due in {{ $time_until_due }}. And, if you're already logged into ADAPT in the
    current browser,
    <a href="{{ $assignment_link }}">this link</a> will take you directly to the assignment.
  </p>

  <p>
    If you would like to change the frequency of these notifications or turn them off altogether, you can visit
    <a href="{{ $notifications_link }}">notifications</a> which can be found under your ADAPT settings.
  </p>

  <p>-ADAPT Support</p>

  <p>
    <strong>This is an automatically generated email. Please do not respond as your email will go unanswered.</strong>
  </p>

  @include('beautymail::templates.sunny.contentEnd')

@stop
