@extends('beautymail::templates.sunny')

@section('content')

  @include ('beautymail::templates.sunny.heading' , [
      'heading' => 'LMS Access Restored',
      'level' => 'h1',
  ])

  @include('beautymail::templates.sunny.contentStart')

  <p>Hi {{ $instructor_first_name }},</p>
  <p>
    Good news — your LMS connection for <strong>{{ $course_name }}</strong> has been restored.
  </p>
  <p>
    Please let your students know they should now go through your LMS again as usual to access and submit their
    assignments.
  </p>
  <p>
    <strong>Reminder:</strong> any assignments your students completed directly through ADAPT while your LMS was
    unavailable will not have had their grades passed back automatically. Please manually reconcile grades for any
    assignments that were open during that time. You can find them under <strong>Assignment Information</strong> and
    <strong>Assignment Gradebook</strong> for the relevant assignment.
  </p>
  <p>-ADAPT Support</p>
  <p><strong>This is an automatically generated email. Please do not respond as your email will go unanswered.</strong>
  </p>
  @include('beautymail::templates.sunny.contentEnd')

@stop
