<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" modal-id="modal-form-errors-course"/>
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-if="!isLoading && [2,5].includes(user.role)">
        <b-card>
          <template #header>
            <div class="d-flex align-items-center justify-content-between">
              <h2 class="h7 m-0">
                General Information
              </h2>
              <ConsultInsight :url="'https://commons.libretexts.org/insight/adapt-course-properties---general-information'"/>
            </div>
          </template>
          <b-card-text>
            <CourseForm :form="editCourseForm" :course="course"/>
            <b-button class="float-right" size="sm" variant="primary" @click="updateCourse">
              Save
            </b-button>
          </b-card-text>
        </b-card>
      </div>
    </div>
  </div>
</template>

<script>
import CourseForm from '~/components/CourseForm'
import Form from 'vform'
import axios from 'axios'
import { mapGetters } from 'vuex'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import AllFormErrors from '~/components/AllFormErrors'
import ConsultInsight from '~/components/ConsultInsight'

export default {
  components: {
    CourseForm,
    AllFormErrors,
    Loading,
    ConsultInsight
  },
  metaInfo () {
    return { title: 'Course General Information' }
  },
  middleware: 'auth',
  data: () => ({
    allFormErrors: [],
    course: {},
    isLoading: true,
    courseId: false,
    editCourseForm: new Form({
      school: '',
      name: '',
      public_description: '',
      private_description: '',
      textbook_url: '',
      start_date: '',
      end_date: '',
      alpha: '0',
      public: '1',
      formative: '0',
      anonymous_users: '0'
    })
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  beforeDestroy () {
    window.removeEventListener('keydown', this.quickSave)
  },
  mounted () {
    window.addEventListener('keydown', this.quickSave)
    this.courseId = this.$route.params.courseId
    this.getCourseInfo(this.courseId)
    console.log(this.courseId)
  },
  methods: {
    quickSave (event) {
      if (event.ctrlKey && event.key === 'S') {
        this.updateCourse()
      }
    },
    async updateCourse () {
      try {
        const { data } = await this.editCourseForm.patch(`/api/courses/${this.courseId}`)
        this.course.is_beta_course = data.is_beta_course
        this.$noty[data.type](data.message)
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.editCourseForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-course')
        }
      }
    },
    async getCourseInfo (courseId) {
      try {
        const { data } = await axios.get(`/api/courses/${courseId}`)
        let course = data.course
        this.course = course
        this.course.id = this.courseId
        this.editCourseForm.school = course.school
        this.editCourseForm.name = course.name
        this.editCourseForm.public_description = course.public_description
        this.editCourseForm.private_description = course.private_description
        this.editCourseForm.term = course.term
        this.editCourseForm.start_date = course.start_date
        this.editCourseForm.end_date = course.end_date
        this.editCourseForm.public = course.public
        this.editCourseForm.alpha = course.alpha
        this.editCourseForm.lms = course.lms
        this.editCourseForm.lms_only_entry = course.lms_only_entry
        this.editCourseForm.adapt_enrollment_notification_date = course.adapt_enrollment_notification_date
        this.editCourseForm.anonymous_users = course.anonymous_users
        this.editCourseForm.formative = course.formative
        this.editCourseForm.untether_beta_course = 0
        this.editCourseForm.textbook_url = course.textbook_url
        this.editCourseForm.whitelisted_domains = course.whitelisted_domains
        if (data.type === 'error') {
          this.$noty.error('We were not able to retrieve the course information.')
          return false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.isLoading = false
    }
  }
}
</script>

<style scoped>

</style>
