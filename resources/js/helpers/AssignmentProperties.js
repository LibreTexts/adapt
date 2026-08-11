import axios from 'axios'
import Form from 'vform'

function reformatTime (vm, time) {
  return vm.$moment(time, 'HH:mm:ss').format('h:mm A')
}

export const defaultFlashcardSettings = {
  autoplay: { enabled: false, student_override: true, seconds: 2 },
  random_shuffle: { enabled: true, student_override: true },
  show_hint: { enabled: true, student_override: true },
  text_to_speech: { enabled: true, student_override: true },
  captions: { enabled: true, student_override: true }
}

export const assignmentForm = new Form({
  name: '',
  assign_tos: [],
  formative: 0,
  assessment_type: 'real time',
  assignment_group_id: null,
  default_open_ended_submission_type: 0,
  can_submit_work: 0,
  can_contact_instructor_auto_graded: 'never',
  default_completion_scoring_mode: '100% for either',
  completion_split_auto_graded_percentage: '50',
  file_upload_mode: 'individual_assessment',
  late_policy: 'not accepted',
  late_deduction_percent: null,
  late_deduction_applied_once: 1,
  late_deduction_application_period: null,
  type_of_submission: 'correct',
  randomizations: 0,
  number_of_randomized_assessments: null,
  // learning tree
  min_number_of_minutes_in_exposition_node: '',
  number_of_successful_paths_for_a_reset: '',
  reset_node_after_incorrect_attempt: 1,
  // end learning tree
  source: 'a',
  scoring_type: 'p',
  include_in_weighted_average: 1,
  num_submissions_needed: '2',
  default_points_per_question: 10,
  external_source_points: 100,
  instructions: '',
  notifications: 1,
  // flashcard
  flashcard_settings: { ...defaultFlashcardSettings }
})

export async function getAssignmentGroups (courseId, noty, vm) {
  let assignmentGroups = [{ value: null, text: 'Please choose one' }]
  let url = courseId
    ? `/api/assignmentGroups/${courseId}`
    : '/api/assignmentGroups'

  try {
    const { data } = await axios.get(url)
    if (data.type === 'error') {
      noty.error(data.message)
      return false
    }
    for (let i = 0; i < data.assignment_groups.length; i++) {
      assignmentGroups.push({
        value: data.assignment_groups[i]['id'],
        text: data.assignment_groups[i]['assignment_group']
      })
    }
    assignmentGroups.push({
      value: -1,
      text: 'Create new group'
    })
  } catch (error) {
    noty.error(error.message)
  }
  return assignmentGroups
}

export async function getAssignmentTemplateOptions () {
  try {
    const { data } = await axios.get('/api/assignment-templates')
    if (data.type !== 'success') {
      this.$noty.error(data.message)
      return false
    }
    if (data.assignment_templates.length) {
      this.assignmentTemplateOptions = [{ text: 'Choose an assignment template', value: null }]
      for (let i = 0; i < data.assignment_templates.length; i++) {
        let assignmentTemplate = data.assignment_templates[i]
        this.assignmentTemplateOptions.push({
          text: assignmentTemplate.template_name,
          value: assignmentTemplate.id
        })
      }
    }
  } catch (error) {
    this.$noty.error(error.message)
  }
}

export function defaultAssignTos (moment, courseStartDate, courseEndDate) {
  return {
    groups: [],
    selectedGroup: null,
    available_from_date: moment(courseStartDate).format('YYYY-MM-DD'),
    available_from_time: '9:00 AM',
    due_date: moment().add(1, 'day').format('YYYY-MM-DD'),
    due_time: '9:00 AM',
    final_submission_deadline_date: moment(courseEndDate).format('YYYY-MM-DD'),
    final_submission_deadline_time: '9:00 AM'
  }
}

export function prepareForm (form) {
  let assignTos = JSON.parse(JSON.stringify(form.assign_tos))
  this.$forceUpdate()
  for (let i = 0; i < form.assign_tos.length; i++) {
    const assignTo = assignTos[i]
    form[`groups_${i}`] = assignTo.groups
    form[`available_from_date_${i}`] = assignTo.available_from_date
    form[`available_from_time_${i}`] = assignTo.available_from_time
    form[`available_from_${i}`] = assignTo.available_from_date + ' ' + assignTo.available_from_time
    form[`final_submission_deadline_date_${i}`] = assignTo.final_submission_deadline_date
    form[`final_submission_deadline_time_${i}`] = assignTo.final_submission_deadline_time
    form[`final_submission_deadline_${i}`] = assignTo.final_submission_deadline_date + ' ' + assignTo.final_submission_deadline_time
    form[`due_date_${i}`] = assignTo.due_date
    form[`due_time_${i}`] = assignTo.due_time
    form[`due_${i}`] = assignTo.due_date + ' ' + assignTo.due_time
  }
}

export function resetAssignmentForm (form, assignmentId) {
  form.name = ''
  form.public_description = ''
  form.private_description = ''
  form.available_from_date = ''
  form.available_from_time = '9:00 AM'
  form.due_date = ''
  form.due_time = '9:00 AM'
  form.type_of_submission = 'correct'
  form.num_submissions_needed = '2'
  form.default_open_ended_submission_type = 'file'
  form.default_points_per_question = '10'
  form.scoring_type = 'p'
  form.default_completion_scoring_mode = '100% for either'
  form.lms_grade_passback = 'automatic'
  form.flashcard_settings = { ...defaultFlashcardSettings }

  assignmentId = 0
  form.errors.clear()
}

export async function initAddAssignment (form, courseId, assignmentGroups, noty, moment, courseStartDate, courseEndDate, bvModal) {
  form.has_submissions_or_file_submissions = 0
  form.solutionsReleased = 0
  form.scoring_type = 'p'
  form.default_completion_scoring_mode = '100% for either'
  form.completion_split_auto_graded_percentage = '50'
  form.assignment_group_id = null
  if (form.is_template) {
    form.assign_to_everyone = 1
  }
  if (!form.is_template) {
    form.assign_tos = [defaultAssignTos(moment, courseStartDate, courseEndDate)]
  }
  form.late_policy = 'not accepted'
  form.can_submit_work = 0
  form.submitted_work_policy = 'optional'
  form.submitted_work_format = []
  form.can_contact_instructor_auto_graded = 'always'
  form.late_deduction_percent = null
  form.late_deduction_applied_once = 1
  form.late_deduction_application_period = null
  form.source = 'a'
  form.default_points_per_question = 10
  form.points_per_question = 'number of points'
  form.total_points = ''
  form.default_clicker_time_to_submit = ''
  form.instructions = ''
  form.assessment_type = 'real time'
  form.number_of_allowed_attempts = '1'
  form.number_of_allowed_attempts_penalty = ''
  form.can_view_hint = 1
  form.hint_penalty = 0
  form.algorithmic = 0
  form.solutions_availability = 'automatic'
  form.file_upload_mode = 'individual_assessment'
  form.number_of_randomized_assessments = null
  form.randomizations = 0
  form.lms_grade_passback = 'automatic'

  // learning tree
  form.min_number_of_minutes_in_exposition_node = ''
  form.number_of_successful_paths_for_a_reset = ''
  form.reset_node_after_incorrect_attempt = 1
  // end learning tree
  form.submission_count_percent_decrease = null
  form.notifications = 1
  form.assign_tos.selectedGroup = null

  // flashcard
  form.flashcard_settings = { ...defaultFlashcardSettings }

  if (!form.is_template) {
    bvModal.show('modal-assignment-properties')
  }
}

export async function editAssignmentProperties (assignmentProperties, vm) {
  vm.originalAssignment = assignmentProperties
  vm.isBetaAssignment = assignmentProperties.is_beta_assignment
  vm.overallStatusIsNotOpen = assignmentProperties.overall_status !== 'Open'
  vm.hasSubmissionsOrFileSubmissions = assignmentProperties.has_submissions_or_file_submissions
  vm.solutionsReleased = assignmentProperties.solutions_released
  vm.number_of_questions = assignmentProperties.num_questions
  vm.form.can_submit_work = assignmentProperties.can_submit_work
  vm.form.submitted_work_format = assignmentProperties.can_submit_work ? JSON.parse(assignmentProperties.submitted_work_format) : []
  vm.form.submitted_work_policy = assignmentProperties.submitted_work_policy
  vm.form.can_contact_instructor_auto_graded = assignmentProperties.can_contact_instructor_auto_graded
  // auto-release
  vm.form.auto_release_shown = assignmentProperties.auto_release_shown
  vm.form.auto_release_show_scores = assignmentProperties.auto_release_show_scores
  vm.form.auto_release_show_scores_after = assignmentProperties.auto_release_show_scores_after
  vm.form.auto_release_solutions_released = assignmentProperties.auto_release_solutions_released
  vm.form.auto_release_solutions_released_after = assignmentProperties.auto_release_solutions_released_after
  vm.form.auto_release_students_can_view_assignment_statistics = assignmentProperties.auto_release_students_can_view_assignment_statistics
  vm.form.auto_release_students_can_view_assignment_statistics_after = assignmentProperties.auto_release_students_can_view_assignment_statistics_after

  if (assignmentProperties.is_template) {
    vm.assignmentTemplateId = assignmentProperties.id
    vm.form.template_name = assignmentProperties.template_name
    vm.form.template_description = assignmentProperties.template_description
    vm.form.assign_to_everyone = assignmentProperties.assign_to_everyone
    if (vm.$route.name === 'instructors.assignments.index') {
      if (vm.form.assign_to_everyone) {
        vm.form.assign_tos[0].groups = [{
          value: { course_id: parseInt(vm.courseId) },
          text: 'Everybody'
        }]
      } else {
        vm.form.assign_tos[0].groups = []
      }
      vm.form.assign_tos.length = 1
      vm.$forceUpdate()
    }
  } else {
    vm.assignmentId = assignmentProperties.id
    vm.form.name = assignmentProperties.name
    vm.form.formative = assignmentProperties.formative
    vm.form.lms_grade_passback = assignmentProperties.lms_grade_passback
    vm.form.lms_api = assignmentProperties.lms_api
    vm.form.assign_tos = assignmentProperties.assign_tos
    console.log(assignmentProperties.assign_tos)
    for (let i = 0; i < assignmentProperties.assign_tos.length; i++) {
      vm.form.assign_tos[i].groups = vm.form.assign_tos[i].formatted_groups
      vm.form.assign_tos[i].selectedGroup = null
      if (vm.form.assign_tos[i].available_from_time) {
        vm.form.assign_tos[i].available_from_time = reformatTime(vm, vm.form.assign_tos[i].available_from_time)
      }
      if (vm.form.assign_tos[i].due_time) {
        vm.form.assign_tos[i].due_time = reformatTime(vm, vm.form.assign_tos[i].due_time)
      }

      vm.form.assign_tos[i].final_submission_deadline_time = assignmentProperties.late_policy !== 'not accepted'
        ? reformatTime(vm, vm.form.assign_tos[i].final_submission_deadline_time)
        : null
    }
  }
  vm.form.algorithmic = assignmentProperties.algorithmic
  vm.form.default_clicker_time_to_submit = assignmentProperties.default_clicker_time_to_submit
  vm.form.solutions_availability = assignmentProperties.solutions_availability
  vm.form.public_description = assignmentProperties.public_description
  vm.form.private_description = assignmentProperties.private_description
  vm.form.assessment_type = vm.assessmentType = assignmentProperties.assessment_type
  vm.form.number_of_allowed_attempts = assignmentProperties.number_of_allowed_attempts
  vm.form.number_of_allowed_attempts_penalty = assignmentProperties.number_of_allowed_attempts_penalty !== null
    ? `${assignmentProperties.number_of_allowed_attempts_penalty}%`
    : ''
  if (vm.form.number_of_allowed_attempts_penalty === '' && ['2', '3', '4', 'unlimited'].includes(vm.form.number_of_allowed_attempts)) {
    vm.form.number_of_allowed_attempts_penalty = '0%'
  }

  vm.form.can_view_hint = parseInt(assignmentProperties.can_view_hint)
  vm.form.hint_penalty = assignmentProperties.hint_penalty !== null
    ? `${assignmentProperties.hint_penalty}%`
    : ''
  if (['', null].includes(vm.form.hint_penalty) && vm.form.can_view_hint) {
    vm.form.hint_penalty = '0%'
  }
  vm.form.shown = assignmentProperties.shown
  vm.form.show_scores = assignmentProperties.show_scores
  vm.form.solutions_released = assignmentProperties.solutions_released
  vm.form.students_can_view_assignment_statistics = assignmentProperties.students_can_view_assignment_statistics

  // learning tree
  vm.form.number_of_successful_paths_for_a_reset = assignmentProperties.number_of_successful_paths_for_a_reset
  vm.form.min_number_of_minutes_in_exposition_node = assignmentProperties.min_number_of_minutes_in_exposition_node
  vm.form.reset_node_after_incorrect_attempt = assignmentProperties.reset_node_after_incorrect_attempt
  // end learning tree

  vm.form.late_policy = assignmentProperties.late_policy
  vm.form.can_change_late_policy = assignmentProperties.can_change_late_policy
  vm.form.late_deduction_applied_once = +(assignmentProperties.late_deduction_application_period === 'once')
  vm.form.late_deduction_application_period = !vm.form.late_deduction_applied_once ? assignmentProperties.late_deduction_application_period : ''
  vm.form.late_deduction_percent = assignmentProperties.late_deduction_percent
  vm.form.assignment_group_id = assignmentProperties.assignment_group_id
  vm.form.include_in_weighted_average = assignmentProperties.include_in_weighted_average
  vm.form.source = assignmentProperties.source
  vm.form.instructions = assignmentProperties.instructions
  vm.form.number_of_randomized_assessments = assignmentProperties.number_of_randomized_assessments
  vm.form.randomizations = assignmentProperties.number_of_randomized_assessments !== null ? 1 : 0
  vm.form.type_of_submission = assignmentProperties.type_of_submission
  vm.form.default_open_ended_submission_type = assignmentProperties.default_open_ended_submission_type
  if (assignmentProperties.default_open_ended_text_editor) {
    vm.form.default_open_ended_submission_type = assignmentProperties.default_open_ended_text_editor + ' ' + assignmentProperties.default_open_ended_submission_type
  }
  vm.form.file_upload_mode = assignmentProperties.file_upload_mode
  vm.form.num_submissions_needed = assignmentProperties.num_submissions_needed
  vm.form.default_points_per_question = assignmentProperties.default_points_per_question
  vm.form.total_points = assignmentProperties.total_points
  vm.showDefaultPointsPerQuestion = assignmentProperties.points_per_question === 'number of points'
  if (vm.showDefaultPointsPerQuestion && !vm.form.default_points_per_question) {
    vm.form.default_points_per_question = 10
  }
  vm.form.points_per_question = assignmentProperties.points_per_question
  vm.form.scoring_type = assignmentProperties.scoring_type
  if (vm.form.scoring_type === 'c') {
    if (assignmentProperties.default_completion_scoring_mode === '100% for either') {
      vm.form.default_completion_scoring_mode = '100% for either'
    } else {
      vm.form.default_completion_scoring_mode = 'split'
      vm.form.completion_split_auto_graded_percentage = assignmentProperties.default_completion_scoring_mode.replace(/\D/g, '')
    }
  }

  vm.form.external_source_points = assignmentProperties.source === 'x' ? assignmentProperties.external_source_points : ''
  vm.form.textbook_url = assignmentProperties.textbook_url
  vm.form.notifications = assignmentProperties.notifications

  // flashcard
  vm.form.flashcard_settings = assignmentProperties.flashcard_settings
    ? (typeof assignmentProperties.flashcard_settings === 'string'
      ? JSON.parse(assignmentProperties.flashcard_settings)
      : assignmentProperties.flashcard_settings)
    : {
      ...defaultFlashcardSettings
    }

  if (!assignmentProperties.modal_already_shown) {
    assignmentProperties.is_template
      ? vm.$bvModal.show('modal-assignment-template')
      : vm.$bvModal.show('modal-assignment-properties')
  }
}
