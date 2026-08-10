@extends('beautymail::templates.sunny')

@section('content')

  @include ('beautymail::templates.sunny.heading' , [
      'heading' => 'LMS Access Temporarily Disabled',
      'level' => 'h1',
  ])

  @include('beautymail::templates.sunny.contentStart')

  <p>Hi {{ $instructor_first_name }},</p>
  <p>
    Your LMS is currently experiencing an outage, so we've temporarily disabled the LMS connection for your course
    <strong>{{ $course_name }}</strong>.
  </p>
  <p>
    In the meantime, your students can still access and complete their assignments directly through ADAPT. Please
    let them know to:
  </p>
  <ol>
    <li>Go to <a href="https://adapt.libretexts.org">adapt.libretexts.org</a></li>
    <li>Click <strong>Login/Register</strong></li>
    <li>Click <strong>Reset your password</strong></li>
  </ol>
  <p>
    Once they've reset their password, they'll be able to log in directly and complete their assignments as usual.
  </p>
  <p>
    <strong>Please note:</strong> grades will not be able to be passed back automatically to your LMS for any
    assignment that a student has not already entered through your LMS at least once. Once your LMS access is
    restored, you'll need to manually reconcile those grades yourself. You can find them under <strong>Assignment
      Information</strong> and <strong>Assignment Gradebook</strong> for the relevant assignment.
  </p>
  <p>We'll email you again as soon as LMS access has been restored for your course.</p>
  <p>-ADAPT Support</p>
  <p><strong>This is an automatically generated email. Please do not respond as your email will go unanswered.</strong>
  </p>
  @include('beautymail::templates.sunny.contentEnd')

@stop
