import _ from 'lodash'

function page (path) {
  return () => import(/* webpackChunkName: '' */ `~/pages/${path}`).then(m => m.default || m)
}

let student_paths = [
  { path: '/students/sitemap', name: 'students.sitemap', component: page('students/sitemap.vue') },
  { path: '/students/courses', name: 'students.courses.index', component: page('students/courses.index.vue') },
  {
    path: '/students/assignments/:assignmentId/summary',
    name: 'students.assignments.summary',
    component: page('students/assignments.summary.vue')
  },
  {
    path: '/students/courses/:courseId/assignments',
    name: 'students.assignments.index',
    component: page('students/assignments.index.vue')
  },
  {
    path: '/students/assignments/:assignmentId/init-formative/:questionId?',
    name: 'assignments.init-formative',
    component: page('students/init_formative_assignment.vue')
  },
  {
    path: '/students/learning-trees/:assignmentId/:learningTreeId/:rootNodeQuestionId/:xCenter',
    name: 'instructors.learning_trees.editor',
    component: page('instructors/learning_trees.editor.vue')
  },
  {
    path: '/students/courses/:courseId/assignments/anonymous-user',
    name: 'students.assignments.anonymous.user.index',
    component: page('students/assignments.anonymous.user.index.vue')
  },
  {
    path: '/not-enrolled-in-lms',
    name: 'not_enrolled_in_lms',
    component: page('students/not_enrolled_in_lms.vue')
  }
]

let instructor_paths = [
  {
    path: '/source/edit/:questionId/:questionMediaUploadId?',
    name: 'EditSource',
    component: page('instructors/edit_question.vue')
  },
  {
    path: '/instructors/assignment-templates',
    name: 'assignments.templates',
    component: page('instructors/assignments.templates.vue')
  },
  {
    path: '/instructors/rubric-templates',
    name: 'rubric.templates',
    component: page('instructors/rubric.templates.vue')
  },
  {
    path: '/instructors/sitemap',
    name: 'instructors.sitemap',
    component: page('instructors/sitemap.vue')
  },
  {
    path: '/instructors/link-assignment-to-lms/:lmsResourceLinkId',
    name: 'link_assignment_to_lms',
    component: page('instructors/link_assignment_to_lms.vue')
  },
  {
    path: '/assignments/:assignmentId/questions/get',
    name: 'questions.get',
    component: page('instructors/questions.get.vue')
  },
  {
    path: '/all-questions/get',
    name: 'all.questions.get',
    component: page('instructors/all_questions.get.vue')
  },
  {
    path: '/all-learning-trees/get',
    name: 'all.learning.trees.get',
    component: page('instructors/all_learning_trees.get.vue')
  },
  {
    path: '/assignments/:assignmentId/learning-trees/get',
    name: 'learning_trees.get',
    component: page('instructors/learning_trees.get.vue')
  },
  {
    path: '/learning-trees/:learningTreeId/get',
    name: 'learning_tree.get',
    component: page('instructors/learning_tree.get.vue')
  },
  {
    path: '/instructors/learning-trees/editor/:learningTreeId?/:fromAllLearningTrees?/:xCenter?',
    name: 'instructors.learning_trees.editor',
    component: page('instructors/learning_trees.editor.vue')
  },
  {
    path: '/instructors/learning-trees',
    name: 'instructors.learning_trees.index',
    component: page('instructors/learning_trees.index.vue')
  },
  {
    path: '/assignments/:assignmentId/questions/:questionId/view',
    name: 'question.view',
    component: page('instructors/question.view.vue')
  },
  {
    path: '/instructors/non-updated-question-revisions/:courseId',
    name: 'course_properties.non_updated_question_revisions',
    component: page('instructors/course_properties/non_updated_question_revisions.vue')
  },
  {
    path: '/assignments/:assignmentId/grading/:questionId?/:studentUserId?',
    name: 'assignment.grading.index',
    component: page('instructors/assignments.grading.vue')
  },
  {
    path: '/testers/courses',
    name: 'testers.courses.index',
    component: page('testers/courses.index.vue')
  },
  {
    path: '/testers/assignments',
    name: 'testers.assignments.index',
    component: page('testers/assignments.index.vue')
  },
  {
    path: '/testers/student-results/:courseId/:assignmentId?',
    name: 'testers.students.results',
    component: page('testers/student.results.vue')
  },
  {
    path: '/testers/logged-in-as-student',
    name: 'logged.in.as.student',
    component: page('testers/logged.in.as.student.vue')
  },
  {
    path: '/testers/cannot-view-as-testing-student',
    name: 'cannot.view.as.testing.student',
    component: page('testers/cannot-view-as-testing-student.vue')
  },
  {
    path: '/instructors/courses/lms/access-granted',
    name: 'LmsAccessGranted',
    component: page('instructors/lms_access_granted.vue')
  },
  {
    path: '/instructors/courses',
    name: 'instructors.courses.index',
    component: page('instructors/courses.index.vue')
  },
  {
    path: '/courses/:courseId/gradebook',
    name: 'gradebook.index',
    component: page('instructors/gradebook.index.vue')
  },
  {
    path: '/instructors/courses/:courseId/assignments',
    name: 'instructors.assignments.index',
    component: page('instructors/assignments.index.vue')
  },
  { path: '/instructors/login-as-single', name: 'loginAsSingle', component: page('control_panel/login.as.vue') }
]
let control_panel_paths = [
  {
    path: '/control-panel',
    component: page('control_panel/index.vue'),
    children: [
      { path: '', redirect: { name: 'login.as' } },
      {
        path: 'login-as',
        name: 'login.as',
        component: page('control_panel/login.as.vue')
      },
      {
        path: 'refresh-question-requests',
        name: 'refresh.question.requests',
        component: page('control_panel/refresh.question.requests.vue')
      },
      { path: 'lti-integrations', name: 'lti.integrations', component: page('control_panel/lti.integrations.vue') },
      {
        path: 'instructor-access-codes',
        name: 'instructorAccessCodes',
        component: page('control_panel/instructor.access.codes.vue')
      },
      {
        path: 'tester-access-codes',
        name: 'testerAccessCodes',
        component: page('control_panel/tester.access.codes.vue')
      },
      {
        path: 'admin.webwork.macros.manager',
        name: 'adminWebworkMacrosManager',
        component: page('control_panel/admin.webwork.macros.manager.vue')
      },
      {
        path: 'webwork-macro-editors',
        name: 'webworkMacroEditors',
        component: page('control_panel/webwork.macro.editors.vue')
      },
      { path: 'question-editors', name: 'questionEditors', component: page('control_panel/question.editors.vue') },
      {
        path: 'courses-to-reset',
        name: 'coursesToReset',
        component: page('control_panel/courses.to.reset.vue')
      },
      {
        path: 'classification-manager',
        name: 'classificationManager',
        component: page('control_panel/classification-manager.vue')
      },
      {
        path: 'discipline-manager',
        name: 'disciplineManager',
        component: page('control_panel/discipline-manager.vue')
      },
      {
        path: 'subject–chapter–section-manager',
        name: 'subjectChapterSectionManager',
        component: page('control_panel/subject–chapter–section-manager.vue')
      },
      {
        path: 'update-user-info',
        name: 'updateUserInfo',
        component: page('control_panel/update_user_info.vue')
      },
      {
        path: 'metrics',
        name: 'metrics',
        component: page('control_panel/metrics.vue')
      },
      {
        path: 'learning-tree-analytics',
        name: 'LearningTreeAnalytics',
        component: page('control_panel/learning_tree_analytics.vue')
      },
      {
        path: 'webwork-submission-errors',
        name: 'WebworkSubmissionErrors',
        component: page('control_panel/webwork_submission_errors.vue')
      },
    ]
  }]
let general_paths = [
  { path: '/too-many-requests', name: 'TooManyRequests', component: page('too_many_requests.vue') },
  { path: '/login-to-view', name: 'LogInToView', component: page('login_to_view.vue') },
  { path: '/beta-course-test', name: 'BetaCourseTest', component: page('beta_course_test.vue') },
  {
    path: '/pending-question-ownership-transfer-request/:action/:token',
    name: 'QuestionOwnershipTransferRequest',
    component: page('instructors/pending_question_ownership_transfer_request.vue')
  },
  {
    path: '/launch-clicker-app/:token/:role/:isRegistration?',
    name: 'LaunchClickerApp',
    component: page('auth/launch.clicker.app.vue')
  },
  { path: '/app-login', name: 'AppLogin', component: page('auth/app_login.vue') },
  {
    path: '/launch-in-new-window/:ltiTokenId/:ltiFinalLocation/:ltiResourceId',
    name: 'launchInNewWindow',
    component: page('launch_in_new_window.vue')
  },
  { path: '/user-jwt-test/:url', component: page('user_jwt_test.vue') },
  { path: '/login-by-jwt/:token', name: 'LoginByJWT', component: page('login_by_jwt.vue') },
  { path: '/lti-login', name: 'ltiLogin', component: page('lti_login.vue') },
  { path: '/incomplete-registration', name: 'incomplete.registration', component: page('incomplete_registration.vue') },
  { path: '/sitemap', name: 'sitemap', component: page('sitemap.vue') },
  { path: '/question-editor/:tab/:questionId?', name: 'question.editor', component: page('question_editor.vue') },
  { path: '/lti/canvas/config/:campusId', name: 'lti_canvas_config', component: page('lti_canvas_config.vue') },
  {
    path: '/lti/brightspace/config/:campusId',
    name: 'lti_brightspace_config',
    component: page('lti_brightspace_config.vue')
  },
  { path: '/canvas/config/api/:campusId', name: 'lti_canvas_config_api', component: page('canvas_config_api.vue') },
  {
    path: '/lti/blackboard/config/:campusId?',
    name: 'lti_blackboard_config',
    component: page('lti_blackboard_config.vue')
  },
  { path: '/lti/moodle/config/:campusId', name: 'lti_moodle_config', component: page('lti_moodle_config.vue') },
  { path: '/imathas-solutions/:jwt', name: 'imathas-solutions', component: page('imathas_solutions.vue') },
  {
    path: '/beta-assignments/redirect-error',
    name: 'beta_assignments_redirect_error',
    component: page('beta_assignments_redirect_error.vue')
  },
  {
    path: '/init-lms-assignment/:assignmentId',
    name: 'init_lms_assignment',
    component: page('init_lms_assignment.vue')
  },
  { path: '/question-in-iframe', name: 'question_in_iframe', component: page('iframe_test.vue') },
  {
    path: '/assignments/:assignmentId/questions/view/:questionId?/:shownSections?',
    name: 'questions.view',
    component: page('questions.view.vue')
  },
  { path: '/submission', name: 'submission.index', component: page('submission.store.vue') },
  { path: '/', name: 'welcome', component: page('welcome.vue') },
  { path: '/login', name: 'login', component: page('auth/login.vue') },
  { path: '/libreone-tester', name: 'LibreOneTester', component: page('auth/libre_one_tester.vue') },
  {
    path: '/auth/no-access',
    name: 'no.access',
    component: page('auth/no.access.vue')
  },
  {
    path: '/finish-sso-registration',
    name: 'finish.sso.registration',
    component: page('auth/finish.sso.registration.vue')
  },
  {
    path: '/register/instructor/:accessCode?',
    name: 'register',
    component: page('auth/register.vue'),
    alias: ['/register/student', '/register/grader']
  },
  {
    path: '/register/question-editor/:accessCode?',
    name: 'question-editor-register',
    component: page('auth/register.vue')
  },
  {
    path: '/register/tester/:accessCode?',
    name: 'tester-register',
    component: page('auth/register.vue')
  },
  { path: '/password/reset', name: 'password.request', component: page('auth/password/email.vue') },
  { path: '/password/reset/:token', name: 'password.reset', component: page('auth/password/reset.vue') },
  { path: '/email/verify/:id', name: 'verification.verify', component: page('auth/verification/verify.vue') },
  { path: '/email/resend', name: 'verification.resend', component: page('auth/verification/resend.vue') },
  { path: '/home', name: 'home', component: page('instructors/courses.index.vue') },
  { path: '/open-courses/:type', name: 'open_courses', component: page('open_courses.vue') },
  { path: '/instructors/frameworks', name: 'frameworks', component: page('instructors/frameworks.vue') },
  {
    path: '/instructors/framework/:frameworkId',
    name: 'framework.view',
    component: page('instructors/framework.view.vue')
  },
  {
    path: '/courses/:courseId/anonymous',
    name: 'anonymous-users-entry',
    component: page('anonymous.users.entry.vue')
  }, {
    path: '/instructors/nursing-analytics',
    name: 'nursing.analytics',
    component: page('instructors/nursing.analytics.vue')
  },
  {
    path: '/settings',
    component: page('settings/index.vue'),
    children: [
      { path: '', redirect: { name: 'settings.profile' } },
      { path: 'profile', name: 'settings.profile', component: page('settings/profile.vue') },
      { path: 'password', name: 'settings.password', component: page('settings/password.vue') },
      { path: 'notifications', name: 'settings.notifications', component: page('settings/notifications.vue') },
      { path: 'linked-accounts', name: 'settings.linked_accounts', component: page('settings/linked_accounts.vue') }
    ]
  },
  { path: '/linked-accounts', name: 'linked_accounts', component: page('linked_accounts.vue') },
  { path: '/notifications', name: 'notifications', component: page('notifications.vue') },
  {
    path: '/courses/:courseId/analytics',
    name: 'CourseAnalytics',
    component: page('instructors/analytics.vue')
  },
  {
    path: '/instructors/assignments/:assignmentId/information',
    component: page('instructors/assignment_information/index.vue'),
    children: [
      { path: '', redirect: { name: 'instructors.assignments.questions' } },
      {
        path: 'questions',
        name: 'instructors.assignments.questions',
        component: page('instructors/assignment_information/questions.vue')
      },
      {
        path: 'case-study-notes',
        name: 'instructors.assignments.case.study.notes',
        component: page('instructors/assignment_information/case.study.notes.vue')
      },
      {
        path: 'summary',
        name: 'instructors.assignments.summary',
        component: page('instructors/assignment_information/summary.vue')
      },
      {
        path: 'properties',
        name: 'instructors.assignments.properties',
        component: page('instructors/assignment_information/properties.vue')
      },
      {
        path: 'control_panel',
        name: 'instructors.assignments.control_panel',
        component: page('instructors/assignment_information/control_panel.vue')
      },
      {
        path: 'overrides',
        name: 'instructors.assignments.overrides',
        component: page('instructors/assignment_information/overrides.vue')
      },
      {
        path: 'auto-graded-submissions',
        name: 'instructors.assignments.auto_graded_submissions',
        component: page('instructors/assignment_information/auto_graded_submissions.vue')
      },
      {
        path: 'grader-access',
        name: 'instructors.assignments.grader_access',
        component: page('instructors/assignment_information/grader_access.vue')
      },
      {
        path: 'resend-grades-to-lms',
        name: 'instructors.assignments.resend_grades_to_lms',
        component: page('instructors/assignment_information/resend_grades_to_lms.vue')
      },
      {
        path: 'statistics',
        name: 'instructors.assignments.statistics',
        component: page('instructors/assignment_information/statistics.vue')
      },
      {
        path: 'gradebook',
        name: 'instructors.assignments.gradebook',
        component: page('instructors/assignment_information/gradebook.vue')
      },
      {
        path: '/assignments/:assignmentId/regrader',
        name: 'assignment.mass_grading.index',
        component: page('instructors/assignments.mass_grading.vue')
      },

    ]
  },
  {
    path: '/invitations/co-instructor/accept/:accessCode',
    name: 'CoInstructorAcceptInvitation',
    component: page('instructors/co_instructors_accept_invitation.vue')
  },
  {
    path: '/centrifugo',
    name: 'centrifugo',
    component: page('centrifugo.vue')
  },
  {
    path: '/new-technologies-demo',
    name: 'NewTechnologiesDemo',
    component: page('new_technologies_demo.vue')
  },
  {
    path: '/instructors/courses/:courseId/properties',
    component: page('instructors/course_properties/index.vue'),
    children: [
      { path: '', redirect: { name: 'course_properties.general_info' } },
      {
        path: 'details',
        name: 'course_properties.general_info',
        component: page('instructors/course_properties/general_info.vue')
      },
      {
        path: 'sections',
        name: 'course_properties.sections',
        component: page('instructors/course_properties/sections.vue')
      },
      {
        path: 'tethered-courses',
        name: 'course_properties.tethered_courses',
        component: page('instructors/course_properties/tethered_courses.vue')
      },
      {
        path: 'letter-grades',
        name: 'course_properties.letter_grades',
        component: page('instructors/course_properties/letter_grades.vue')
      },
      {
        path: 'edit-assignment-dates',
        name: 'course_properties.edit_assignment_dates',
        component: page('instructors/course_properties/edit_assignment_dates.vue')
      },
      {
        path: 'co-instructors',
        name: 'course_properties.co_instructors',
        component: page('instructors/course_properties/co_instructors.vue')
      },
      {
        path: 'graders',
        name: 'course_properties.graders',
        component: page('instructors/course_properties/graders.vue')
      },
      {
        path: 'ungraded-submissions',
        name: 'course_properties.ungraded_submissions',
        component: page('instructors/course_properties/ungraded_submissions.vue')
      },
      {
        path: 'students',
        name: 'course_properties.students',
        component: page('instructors/course_properties/students.vue')
      },
      {
        path: 'autoRelease',
        name: 'course_properties.auto_release',
        component: page('instructors/course_properties/auto_release.vue')
      },
      {
        path: 'a11y-redirect',
        name: 'course_properties.a11y_redirect',
        component: page('instructors/course_properties/a11y_redirect.vue')
      },
      {
        path: 'iframe-properties',
        name: 'course_properties.iframe_properties',
        component: page('instructors/course_properties/iframe_properties.vue')
      },
      {
        path: 'reset',
        name: 'course_properties.reset_course',
        component: page('instructors/course_properties/reset_course.vue')
      },
      {
        path: 'testers',
        name: 'course_properties.testers',
        component: page('instructors/course_properties/testers.vue')
      }
    ]
  },
  { path: '*', component: page('errors/404.vue') }
]

export default _.concat([{
  path: '/h5p',
  name: 'h5p',
  component: page('h5p.vue')
}], general_paths, student_paths, instructor_paths, control_panel_paths)
