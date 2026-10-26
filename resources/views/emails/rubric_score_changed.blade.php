@extends('beautymail::templates.sunny')

@section('content')

  @include ('beautymail::templates.sunny.heading' , [
      'heading' => 'Score Updated',
      'level' => 'h1',
  ])

  @include('beautymail::templates.sunny.contentStart')

  <p>Hi {{ $student_first_name }},</p>
  @if (!empty($cleared))
    <p>The grading rubric for <strong>{{ $question_title }}</strong> in
      <strong>{{ $assignment_name }}</strong> was updated. Your score of <strong>{{ $old_score }}</strong> out of
      {{ $question_points }} for that question was cleared, and your work will be regraded with the new rubric.</p>
  @else
    <p>The grading rubric for <strong>{{ $question_title }}</strong> in
      <strong>{{ $assignment_name }}</strong> was updated, and your score for that question changed from
      <strong>{{ $old_score }}</strong> to <strong>{{ $new_score }}</strong> out of {{ $question_points }}.</p>
  @endif
  <p>You can view the assignment here: <a href="{{ $link }}">{{ $link }}</a></p>
  <p>If you have any questions about this change, please reach out to your instructor, as replies to this email will
    not be answered.</p>

  <p>-ADAPT Support</p>
  @include('beautymail::templates.sunny.contentEnd')

@stop
