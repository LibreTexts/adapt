<template>
  <div>
    <div class="vld-parent">
      <PageTitle title="Non-Updated Revisions" />
      <UpdateRevision ref="UpdateRevision"
                      :key="`update-revision-${questionRevisionDifferencesKey}`"
                      :assignment-id="nonUpdatedQuestionRevisions[currentQuestionIndex - 1] ? nonUpdatedQuestionRevisions[currentQuestionIndex - 1].assignment_id : 0"
                      :current-question="nonUpdatedQuestionRevisions[currentQuestionIndex - 1]"
                      :pending-question-revision="latestQuestionRevision"
                      :latest-question-revision-id="latestQuestionRevision.id"
                      assignment-name="name"
                      :question-number="currentQuestionIndex"
                      :submission-warning-only="true"
                      @reloadSingleQuestion="removeCurrentQuestion"
      />
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <b-modal id="modal-confirm-update-all-topical"
               title="Confirm Topical Changes"
               no-close-on-backdrop
               @hidden="updateAllChangesAreTopical=false"
      >
        <p>
          Updates every question in this course to its latest revision without touching student submissions.
        </p>
        <b-alert variant="danger" show>
          If any changes are non-topical in nature, assignments with those questions may not load for students.
        </b-alert>
        <b-form-checkbox
          id="update-all-changes-are-topical"
          v-model="updateAllChangesAreTopical"
          name="update_all_changes_are_topical"
          :value="true"
          :unchecked-value="false"
        >
          The changes I made are topical in nature.
        </b-form-checkbox>
        <template #modal-footer>
          <b-button size="sm"
                    @click="$bvModal.hide('modal-confirm-update-all-topical')"
          >
            Cancel
          </b-button>
          <b-button size="sm"
                    variant="primary"
                    @click="confirmUpdateAllTopical"
          >
            Submit
          </b-button>
        </template>
      </b-modal>
      <b-modal id="modal-update-all-students-enrolled"
               title="All Student Submissions Removed"
               @hidden="understandStudentSubmissionsRemoved=false"
      >
        <b-alert variant="danger" show>
          <b-form-checkbox
            id="submissions-from-all-questions-removed"
            v-model="understandStudentSubmissionsRemoved"
            name="student_submissions_removed"
            :value="true"
            :unchecked-value="false"
          >
            I understand that student submissions for every question in this course will be removed. Please inform your
            class to
            resubmit.
          </b-form-checkbox>
        </b-alert>
        <template #modal-footer="{ cancel, ok }">
          <b-button size="sm"
                    variant="primary"
                    @click="updateAllQuestionRevisions"
          >
            Update All
          </b-button>
          <b-button size="sm"
                    @click="$bvModal.hide('modal-update-all-students-enrolled')"
          >
            Cancel
          </b-button>
        </template>
      </b-modal>

      <div v-if="!isLoading">
        <div v-if="!isBetaCourse">
          <p>
            While there are no students enrolled in your course, you can turn auto-update on which will automatically
            update your questions to the latest revision. Regardless of whether there are students enrolled, you can
            manually update any assignment questions in your course. In this case, if student submissions exists, the
            submissions will be removed and
            the scores will be updated.
          </p>
          <b-alert :show="enrolledUsers">
            There are students enrolled in this course.
            <span v-if="autoUpdateQuestionRevisions">No questions will auto-update while students are enrolled in the
              course.</span>
            <span v-if="!autoUpdateQuestionRevisions">You will not be able to turn on the auto-update functionality.</span>
          </b-alert>
          <p v-if="!enrolledUsers">
            Auto-Update
            <toggle-button
              class="mt-2"
              :width="60"
              :value="autoUpdateQuestionRevisions"
              :sync="true"
              :font-size="14"
              :margin="4"
              :color="toggleColors"
              :labels="{checked: 'Yes', unchecked: 'No'}"
              @change="updateAutoUpdateQuestionRevisions"
            />
          </p>
          <hr>
        </div>
        <div v-if="nonUpdatedQuestionRevisions.length">
          <div v-if="powerUser || !enrolledUsers" class="text-center mb-2 mt-2">
            <span v-show="!updatingAllQuestionRevisions">
              <b-button variant="primary" size="sm" @click="initUpdateAll">
                Update All
              </b-button>
            </span>
            <span v-show="updatingAllQuestionRevisions" class="pl-2">
              <b-button variant="primary" disabled size="sm">
                <b-spinner small type="grow" />
                Processing...
              </b-button>
            </span>
          </div>
          <div class="overflow-auto">
            <b-pagination
              v-model="currentQuestionIndex"
              :total-rows="nonUpdatedQuestionRevisions.length"
              per-page="1"
              align="center"
              first-number
              last-number
              limit="17"
              @input="changePage()"
            />
            <div v-if="nonUpdatedQuestionRevisions[currentQuestionIndex - 1]">
              <h5>
                <router-link
                  :to="{ name: 'questions.view', params: {assignmentId:nonUpdatedQuestionRevisions[currentQuestionIndex - 1].assignment_id, questionId: nonUpdatedQuestionRevisions[currentQuestionIndex - 1].question_id} }"
                >
                  {{ nonUpdatedQuestionRevisions[currentQuestionIndex - 1].assignment_name }}:
                  {{
                    nonUpdatedQuestionRevisions[currentQuestionIndex - 1].custom_question_title
                      ? nonUpdatedQuestionRevisions[currentQuestionIndex - 1].custom_question_title
                      : nonUpdatedQuestionRevisions[currentQuestionIndex - 1].title
                  }}
                </router-link>
              </h5>
              ADAPT ID: <span id="adapt-id">{{
                nonUpdatedQuestionRevisions[currentQuestionIndex - 1].assignment_id
              }}-{{ nonUpdatedQuestionRevisions[currentQuestionIndex - 1].question_id }}</span> <span class="text-info">
                <a href=""
                   aria-label="Copy ADAPT ID"
                   @click.prevent="doCopy('adapt-id')"
                >
                  <font-awesome-icon :icon="copyIcon" />
                </a>
                <b-button variant="primary" size="sm"
                          @click="initUpdateQuestionRevision(nonUpdatedQuestionRevisions[currentQuestionIndex - 1].assignment_id,nonUpdatedQuestionRevisions[currentQuestionIndex - 1].question_id)"
                >Update Question</b-button>
              </span>
              <div class="mt-3">
                <QuestionRevisionDifferences :key="`question-revision-differences-${questionRevisionDifferencesKey}`"
                                             :revision1="currentQuestionRevision"
                                             :revision2="latestQuestionRevision"
                                             :show-current-latest-text="true"
                                             :math-jax-rendered="mathJaxRendered"
                                             :diffs-shown="diffsShown"
                                             @reloadQuestionRevisionDifferences="reloadQuestionRevisionDifferences"
                />
              </div>
            </div>
          </div>
        </div>
        <div v-if="!nonUpdatedQuestionRevisions.length">
          <b-alert show variant="info">
            All of your question revisions are up-to-date.
          </b-alert>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { ToggleButton } from 'vue-js-toggle-button'
import QuestionRevisionDifferences from '~/components/QuestionRevisionDifferences.vue'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { doCopy } from '~/helpers/Copy'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { mapGetters } from 'vuex'
import en from 'vue-upload-component/docs/i18n/en'
import UpdateRevision from '~/components/questions/UpdateRevision.vue'
import { initCentrifuge } from '~/helpers/Centrifuge'

export default {
  metaInfo () {
    return { title: 'Auto-Update Question Revisions' }
  },
  components: {
    UpdateRevision,
    FontAwesomeIcon,
    QuestionRevisionDifferences,
    Loading,
    ToggleButton
  },
  computed: {
    en () {
      return en
    },
    ...mapGetters({
      user: 'auth/user'
    }),
    isAdmin: () => window.config.isAdmin
  },
  // eslint-disable-next-line vue/order-in-components
  data: () => ({
    updatingAllQuestionRevisions: false,
    centrifuge: {},
    isBetaCourse: false,
    understandStudentSubmissionsRemoved: false,
    updateAllChangesAreTopical: false,
    powerUser: false,
    enrolledUsers: false,
    activeAssignmentId: 0,
    activeQuestionId: 0,
    diffsShown: true,
    mathJaxRendered: false,
    copyIcon: faCopy,
    courseId: 0,
    courseName: '',
    questionRevisionDifferencesKey: 0,
    currentQuestionRevision: {},
    latestQuestionRevision: {},
    currentQuestionIndex: 1,
    isLoading: true,
    toggleColors: window.config.toggleColors,
    autoUpdateQuestionRevisions: false,
    nonUpdatedQuestionRevisions: []
  }),
  mounted () {
    console.log(this.$refs)
    window.addEventListener('keydown', this.hotKeys)
    this.courseId = this.$route.params.courseId
    this.powerUser = this.isAdmin
    this.getCourseInfo(this.courseId)
    this.getNonUpdatedAssignmentQuestionsByCourse(this.courseId)
  },
  beforeDestroy () {
    window.removeEventListener('keydown', this.hotKeys)
    try {
      if (this.centrifuge) {
        this.centrifuge.disconnect()
      }
    } catch (error) {
      // won't be a function for all the other ones that haven't been defined on the page
    }
  },
  methods: {
    removeCurrentQuestion () {
      this.nonUpdatedQuestionRevisions.splice(this.currentQuestionIndex - 1, 1)
      this.currentQuestionIndex = this.currentQuestionIndex > 1 ? this.currentQuestionIndex - 1 : 1
    },
    hotKeys (event) {
      if (event.key === 'ArrowRight') {
        if (!this.nonUpdatedQuestionRevisions[this.currentQuestionIndex]) {
          return false
        }
        this.currentQuestionIndex++
      }
      if (event.key === 'ArrowLeft' && this.currentQuestionIndex > 1) {
        this.currentQuestionIndex--
      }
      this.changePage()
    },
    reloadQuestionRevisionDifferences (mathJaxRendered, diffsShown) {
      this.mathJaxRendered = mathJaxRendered
      this.diffsShown = diffsShown
      this.questionRevisionDifferencesKey++
    },
    async initUpdateQuestionRevision (assignmentId, questionId) {
      this.activeAssignmentId = assignmentId
      this.activeQuestionId = questionId
      try {
        const { data } = await axios.get(`/api/enrollments/assignment/${this.activeAssignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        data.students_enrolled
          ? this.$bvModal.show('modal-update-question-revision-students-enrolled')
          : await this.updateQuestionRevision()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateQuestionRevision () {
      this.$bvModal.show('modal-show-revision')
    },
    initUpdateAll () {
      if (this.powerUser) {
        this.$bvModal.show('modal-confirm-update-all-topical')
        return
      }
      this.proceedWithUpdateAll()
    },
    confirmUpdateAllTopical () {
      if (!this.updateAllChangesAreTopical) {
        this.$noty.info('Please check the box stating that you agree that the changes are topical in nature.')
        return false
      }
      // Capture before hiding: @hidden resets updateAllChangesAreTopical to false, and that
      // reset can land before updateAllQuestionRevisions gets to read it (it awaits
      // initCentrifuge() first), so we pass the confirmed value through explicitly instead
      // of relying on reactive state read after an async gap.
      const changesAreTopical = this.updateAllChangesAreTopical
      this.$bvModal.hide('modal-confirm-update-all-topical')
      // Admin + confirmed topical means submissions are never touched, so the
      // separate "submissions will be erased" confirmation doesn't apply here.
      this.updateAllQuestionRevisions(changesAreTopical)
    },
    proceedWithUpdateAll () {
      if (this.enrolledUsers) {
        this.$bvModal.show('modal-update-all-students-enrolled')
        return false
      } else {
        this.understandStudentSubmissionsRemoved = true
      }
      this.updateAllQuestionRevisions()
    },
    async updateAllQuestionRevisions (changesAreTopical = this.updateAllChangesAreTopical) {
      if (!this.powerUser && this.enrolledUsers && !this.understandStudentSubmissionsRemoved) {
        this.$noty.info('Please check the box before submitting.')
        return false
      }
      this.centrifuge = await initCentrifuge()
      const sub = this.centrifuge.newSubscription(`update-all-question-revisions-${this.courseId}`)
      console.log(sub)
      const updatedAllQuestionRevisions = async (ctx) => {
        this.$noty[ctx.data.type](ctx.data.message)
        this.centrifuge.disconnect()
        await this.getNonUpdatedAssignmentQuestionsByCourse(this.courseId)
        this.$nextTick(() => {
          this.updatingAllQuestionRevisions = false
        })
      }
      sub.on('publication', function (ctx) {
        console.log(ctx)
        updatedAllQuestionRevisions(ctx)
      }).subscribe()
      this.updatingAllQuestionRevisions = true
      this.$bvModal.hide('modal-update-all-students-enrolled')
      try {
        const { data } = await axios.patch(`/api/non-updated-question-revisions/update-to-latest/course/${this.courseId}`, {
          understand_student_submissions_removed: this.understandStudentSubmissionsRemoved,
          changes_are_topical: changesAreTopical
        })
        if (data.type === 'error') {
          this.$noty.error(data.message)
          this.updatingAllQuestionRevisions = false
        } else {
          this.updatingAllQuestionRevisions = true
        }
      } catch (error) {
        this.$noty.error(error.message)
        this.updatingAllQuestionRevisions = false
      }
    },
    doCopy,
    async changePage () {
      let question = this.nonUpdatedQuestionRevisions[this.currentQuestionIndex - 1]
      if (question) {
        let questionId = question.question_id
        this.nonUpdatedQuestionRevisions[this.currentQuestionIndex - 1].id = questionId
        try {
          const { data } = await axios.get(`/api/question-revisions/question/${questionId}`)
          if (data.type === 'error') {
            this.$noty.error(data.message)
            return false
          }
          this.currentQuestionRevision = data.revisions.find(item => item.id === question.current_question_revision_id)
          this.latestQuestionRevision = data.revisions.find(item => item.id === question.latest_question_revision_id)
          this.questionRevisionDifferencesKey++
          console.log(data)
        } catch (error) {
          this.$noty.error(error.message)
        }
      }
    },
    async getNonUpdatedAssignmentQuestionsByCourse (courseId) {
      const { data } = await axios.get(`/api/non-updated-question-revisions/course/${courseId}`)
      if (data.type === 'error') {
        this.$noty.error(data.message)
      }
      this.nonUpdatedQuestionRevisions = data.non_updated_question_revisions
      await this.changePage()
    },
    async getCourseInfo (courseId) {
      try {
        const { data } = await axios.get(`/api/courses/${courseId}`)
        if (data.type === 'error') {
          this.isLoading = false
          this.$noty.error('We were not able to retrieve the course information.')
          return false
        }
        this.autoUpdateQuestionRevisions = this.enrolledUsers ? false : !!data.course.auto_update_question_revisions
        this.courseName = data.course.name
        this.enrolledUsers = data.course.enrolled_users
        this.isBetaCourse = data.course.is_beta_course
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.isLoading = false
    },
    async updateAutoUpdateQuestionRevisions () {
      if (this.enrolledUsers && !this.autoUpdateQuestionRevisions) {
        this.$noty.info('Auto-update cannot be turned on while there are students enrolled in the course.')
        return false
      }
      try {
        const { data } = await axios.patch(`/api/courses/${this.courseId}/auto-update-question-revisions`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.autoUpdateQuestionRevisions = !this.autoUpdateQuestionRevisions
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>

<style scoped>

</style>
