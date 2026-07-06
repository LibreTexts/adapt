<template>
  <div>
    <b-modal
      id="modal-question-editor"
      title="New Question"
      size="xl"
      hide-footer
      no-close-on-backdrop
    >
      <CreateQuestion modal-id="modal-createQuestion-question-editor"
                      :assignment-id="+assignmentId"
                      :parent-get-my-questions="reloadAssignmentQuestions"
      />
    </b-modal>
    <div v-if="[2, 4, 5].includes(user.role)">
      <CannotAddAssessmentToBetaAssignmentModal/>
      <b-container>
        <hr>
      </b-container>
      <div v-show="showPanel" class="row">
        <div class="mt-2 mb-2">
          <div class="mb-1">
            <b-button v-if="[2,5].includes(user.role)"
                      class="btn-block text-left view-questions-btn"
                      variant="link"
                      @click="viewQuestions"
            >
              <b-icon-eye
                scale="1.25"
                class="pr-1"
              />
              View {{ capitalFormattedAssessmentType }}s
            </b-button>
          </div>
          <div class="mb-1">
            <b-button v-if="[2,5].includes(user.role)"
                      class="btn-block text-left"
                      variant="primary"
                      @click="getAssessmentsForAssignment(assignmentId)"
            >
              <b-icon-plus
                scale="1.25"
                class="pr-1"
              />
              Add {{ capitalFormattedAssessmentType }}s
            </b-button>
          </div>
          <div>
            <b-button v-if="[2,5].includes(user.role)"
                      class="btn-block text-left"
                      variant="info"
                      @click="openQuestionEditor()"
            >
              <b-icon-pencil-square
                scale="1.25"
                class="pr-1"
              />
              New {{ capitalFormattedAssessmentType }}
            </b-button>
          </div>
          <b-card v-show="showPanel" header-html="<h2 class=&quot;h7&quot;>Assignment Information</h2>"
                  class="properties-card mt-3 mb-2"
          >
            <ul class="nav flex-column nav-pills">
              <template v-for="(tab, index) in tabs1">
                <li v-if="showTab(tab.name)" :key="`tab-${index}`" class="nav-item">
                  <router-link
                    v-if="showTab(tab.name)"
                    :key="tab.route"
                    :to="{ name: tab.route }"
                    class="nav-link"
                    active-class="active"
                  >
                    <span class="hover-underline"> {{ tab.name }}</span>
                  </router-link>
                </li>
              </template>
            </ul>
          </b-card>
          <b-card body-class="p-0" class="mb-2" v-if="tabs2.some(tab => showTab(tab.name))">
            <ul class="nav flex-column nav-pills">
              <template v-for="(tab, index) in tabs2">
                <li v-if="showTab(tab.name)" :key="`tab-${index}`" class="nav-item">
                  <router-link
                    v-if="showTab(tab.name)"
                    :key="tab.route"
                    :to="{ name: tab.route }"
                    class="nav-link"
                    active-class="active"
                  >
                    <span class="hover-underline"> {{ tab.name }}</span>
                  </router-link>
                </li>
              </template>
            </ul>
          </b-card>
          <b-card v-show="showTab('Regrader') || (user.role !== 5 && !isFormative) || (user.role !== 5 && isLms)"
                  body-class="p-0"
          >
            <ul class="nav flex-column nav-pills">
              <li v-if="showTab('Regrader')" class="nav-item">
                <router-link
                  v-if="showTab('Regrader')"
                  :to="{ name: 'assignment.mass_grading.index' }"
                  class="nav-link"
                  active-class="active"
                >
                  <span class="hover-underline"> Regrader</span>
                </router-link>
              </li>
              <li>
                <router-link
                  :to="{ name: 'assignment.grading.index', params: {assignmentId: assignmentId}}"
                  class="nav-link"
                  active-class="active"
                >
                  <span class="hover-underline"> Open Grader</span>
                </router-link>
              </li>
              <router-link v-if="user.role !== 5 && !isFormative"
                           :to="{ name: 'instructors.assignments.gradebook' }"
                           class="nav-link"
                           active-class="active"
              >
                <span class="hover-underline"> Assignment Gradebook</span>
              </router-link>
              <li v-if="user.role !== 5 && !isFormative">
                <a :href="`/courses/${courseId}/gradebook`" class="nav-link">
                  <span class="hover-underline">  Course Gradebook</span>
                </a>
              </li>
              <router-link v-if="user.role !== 5 && isLms"
                           :to="{ name: 'instructors.assignments.resend_grades_to_lms' }"
                           class="nav-link"
                           active-class="active"
              >
                <span class="hover-underline"> Resend Grades to LMS</span>
              </router-link>
            </ul>
          </b-card>
        </div>

        <div class="col-md-9">
          <transition name="fade" mode="out-in">
            <router-view :key="`router-view-${tabKey}`"/>
          </transition>
        </div>
      </div>
    </div>
  </div>
</template>

<script>

import { mapGetters } from 'vuex'
import axios from 'axios'
import CannotAddAssessmentToBetaAssignmentModal from '~/components/CannotAddAssessmentToBetaAssignmentModal'
import CreateQuestion from '~/components/questions/CreateQuestion'
import { updateModalToggleIndex } from '~/helpers/accessibility/fixCKEditor'

export default {
  middleware: 'auth',
  components: {
    CannotAddAssessmentToBetaAssignmentModal,
    CreateQuestion
  },
  data: () => ({
    assessmentType: '',
    isLms: false,
    showPanel: false,
    isFormative: false,
    tabKey: 0,
    assignmentId: 0,
    isBetaAssignment: false,
    courseId: 0
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    capitalFormattedAssessmentType () {
      return this.assessmentType === 'learning tree' ? 'Tree' : 'Question'
    },
    isAdmin: () => window.config.isAdmin,
    tabs1 () {
      return [
        {
          icon: '',
          name: 'Case Study Notes',
          route: 'instructors.assignments.case.study.notes'
        },
        {
          icon: '',
          name: 'Summary',
          route: 'instructors.assignments.summary'
        },
        {
          icon: '',
          name: 'Properties',
          route: 'instructors.assignments.properties'
        },
        {
          icon: '',
          name: 'Control Panel',
          route: 'instructors.assignments.control_panel'
        }
      ]
    },
    tabs2 () {
      return [
        {
          icon: '',
          name: 'Grader Access',
          route: 'instructors.assignments.grader_access'
        },
        {
          icon: '',
          name: 'Submission Overrides',
          route: 'instructors.assignments.submission_overrides'
        },
        {
          icon: '',
          name: 'Submissions',
          route: 'instructors.assignments.auto_graded_submissions'
        },
        {
          icon: '',
          name: 'Statistics',
          route: 'instructors.assignments.statistics'
        }
      ]
    }
  },
  mounted () {
    if (![2, 4, 5].includes(this.user.role)) {
      this.$router.push({ name: 'no.access' })
      return false
    }
    this.assignmentId = this.$route.params.assignmentId
    this.getAssignmentSummary()
  },
  methods: {
    viewQuestions () {
      this.$router.push({ name: 'instructors.assignments.questions', params: { assignmentId: this.assignmentId } })
    },
    reloadAssignmentQuestions () {
      this.$bvModal.hide('modal-question-editor')
      this.getAssignmentSummary()
      this.tabKey++
      this.$forceUpdate()
    },
    openQuestionEditor () {
      if (this.assessmentType === 'learning tree') {
        window.location.href = '/instructors/learning-trees/editor/0'
      } else {
        this.$bvModal.show('modal-question-editor')
        this.$nextTick(() => {
          updateModalToggleIndex('modal-question-editor')
        })
      }
    },
    showTab (name) {
      if (name === 'Lab Report') {
        return this.isAdmin || ['dlarsen@ucdavis.edu', 'bjcutler@ucdavis.edu'].includes(this.user.email)
      }
      if (this.isFormative && !['Questions', 'Case Study Notes', 'Properties'].includes(name)) {
        return false
      }
      return (this.user.role === 5 && ['Questions', 'Properties'].includes(name)) ||
        this.user.role === 2 ||
        (this.user.role === 4 && !['Grader Access', 'Properties'].includes(name))
    },
    gotoMassGrading () {
      this.$router.push(`/assignments/${this.assignmentId}/regrader`)
    },
    getAssessmentsForAssignment (assignmentId) {
      this.isBetaAssignment
        ? this.$bvModal.show('modal-cannot-add-assessment-to-beta-assignment')
        : this.$router.push(`/assignments/${assignmentId}/${this.assessmentUrlType}/get`)
    },
    getStudentView (assignmentId) {
      this.$router.push(`/assignments/${assignmentId}/questions/view`)
    },
    async getAssignmentSummary () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/summary`)
        console.log(data)
        this.showPanel = true
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.courseId = data.assignment.course_id
        this.isFormative = data.assignment.is_formative_course || data.assignment.formative
        this.isBetaAssignment = data.assignment.is_beta_assignment
        this.assessmentType = data.assignment.assessment_type
        this.assessmentUrlType = this.assessmentType === 'learning tree' ? 'learning-trees' : 'questions'
        this.isLms = data.assignment.lms
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.showPanel = true
    }
  }
}
</script>

<style>
.properties-card .card-body {
  padding: 0;
}
</style>
