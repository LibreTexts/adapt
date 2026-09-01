<template>
  <div>
    <Email id="modal-contact-us-for-instructor-account"
           ref="email"
           extra-email-modal-text="To obtain an instructor account, please provide your name, your email address, and an optional message."
           title="Contact Us For Instructor Account"
           type="contact_us"
           subject="Request Instructor Access Code"
    />
    <b-modal id="modal-delete-discipline"
             title="Delete Discipline"
             no-close-on-backdrop
    >
      <p>Please confirm that you would like to delete the discipline:</p>
      <p class="text-center font-weight-bold">
        {{ activeDiscipline.text }}
      </p>
      Once this discipline is deleted, it will be removed from all associated courses.

      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-delete-discipline')"
        >
          Cancel
        </b-button>
        <b-button
          variant="danger"
          size="sm"
          class="float-right"
          @click="deleteDiscipline(activeDiscipline.value)"
        >
          Delete
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-edit-new-discipline"
             :title="activeDiscipline.value ? 'Edit Discipline' : 'New Discipline'"
             no-close-on-backdrop
             @hidden="resetDiscipline"
    >
      <b-form-input id="description"
                    v-model="disciplineForm.name"
                    :class="{ 'is-invalid': disciplineForm.errors.has('name') }"
                    style="width:400px"
                    type="text"
                    @keydown="disciplineForm.errors.clear('name')"
      />
      <has-error :form="disciplineForm" field="name"/>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="disciplineForm.errors.clear();$bvModal.hide('modal-edit-new-discipline')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="saveDiscipline(activeDiscipline.value)"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-update-disciplines"
             title="Update Disciplines"
             size="lg"
             no-close-on-backdrop
    >
      <div class="mb-2">
        <b-button variant="primary"
                  size="sm"
                  @click="$bvModal.show('modal-edit-new-discipline')"
        >
          New Discipline
        </b-button>
      </div>
      <ol v-show="disciplineOptions.length">
        <li v-for="(discipline, disciplineIndex) in disciplineOptions"
            v-show="discipline.value"
            :key="`discipline-${disciplineIndex}`"
        >
          {{ discipline.text }}
          <b-icon icon="pencil"
                  @click.prevent="initEditDiscipline(discipline)"
          />
          <b-icon icon="trash"
                  @click.prevent="initDeleteDiscipline(discipline)"
          />
        </li>
      </ol>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-update-disciplines')"
        >
          Cancel
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-enter-course"
      title="Enter Course"
    >
      <p>
        To enter this course, you'll need to log in with your instructor account and then visit the {{ title }} from the
        Dashboard.</p>
       <p>If you don’t have a LibreTexts Instructor Account apply at <a href="https://register.libretexts.org" target="_blank" rel="noopener noreferrer">https://register.libretexts.org</a>. We do require an institutional email address for security reasons and a URL to a page showing that you are an instructor.
         All LibreTexts Instructor Accounts provide access to the ADAPT homework system as well as our textbook platform. </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="user ? logout(): beginLogin"
        >
          Log In
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-assignments"
      ref="modalAssignments"
      title="Assignments"
      hide-footer
    >
      <ul>
        <li v-for="assignment in assignments" :key="assignment.id">
          {{ assignment.name }}
        </li>
      </ul>
    </b-modal>
    <PageTitle :title="title"/>
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-show="type === 'commons'" class="mb-4">
        Enter full or partial text in the title and/or description to filter out the courses. For example, if you
        type
        "span" in the title field, the courses "Spanish I" and "Spanning the Universe" will be in the filtered
        list.
        <hr>
      </div>
      <b-form-group
        label-cols-sm="2"
        label-cols-lg="1"
        label-for="discipline"
        label="Discipline"
      >
        <div>
          <b-form-select v-model="discipline"
                         style="width:300px"
                         size="sm"
                         :options="disciplineOptions"
                         class="mt-2 mr-2"
          />
          <b-button v-show="isAdmin"
                    size="sm"
                    variant="outline-primary"
                    style="height:30px;margin-top:8px"
                    @click="$bvModal.show('modal-update-disciplines')"
          >
            Update Disciplines
          </b-button>
        </div>
      </b-form-group>
      <b-form-group
        label-cols-sm="2"
        label-cols-lg="1"
        label-for="title"
      >
        <template v-slot:label>
          Title
          <span v-show="type === 'public_courses'">
            <QuestionCircleTooltip id="title-tooltip"/>
            <b-tooltip target="title-tooltip"
                       delay="250"
                       triggers="hover focus"
            >
              Enter any partial text. For example "span" will find Spanish II as well as Introductory Spanish
            </b-tooltip>
          </span>
        </template>
        <b-form-input id="title"
                      v-model="name"
                      style="width:300px"
                      aria-label="title"
                      type="text"
                      size="sm"
        />
      </b-form-group>
      <div v-show="type === 'commons'">
        <b-form-group
          label-cols-sm="2"
          label-cols-lg="1"
          label-for="description"
        >
          <template v-slot:label>
            Description
          </template>
          <b-form-input id="description"
                        v-model="description"
                        style="width:300px"
                        aria-label="description"
                        size="sm"
                        type="text"
          />
        </b-form-group>
      </div>
      <b-form-group
        v-show="type === 'public'"
        label-cols-sm="2"
        label-cols-lg="1"
        label-for="instructor"
        label="Author"
      >
        <div style="width:500px">
          <autocomplete
            ref="authorSearch"
            :search="searchByAuthor"
            @submit="selectAuthor"
          />
        </div>
      </b-form-group>
      <b-form-group
        v-show="type === 'public'"
        label-cols-sm="2"
        label-cols-lg="1"
        label-for="school"
        label="School"
      >
        <div style="width:500px">
          <autocomplete
            id="school"
            ref="schoolSearch"
            :search="searchBySchool"
            @submit="selectSchool"
          />
        </div>
      </b-form-group>
      <div class="mb-3">
        <b-button variant="primary" size="sm" @click="update">
          Update
        </b-button>
        <span class="ml-2"><b-button size="sm" @click="reset">Reset</b-button></span>
      </div>

      <b-table
        v-show="openCourses.length"
        :aria-label="title"
        striped
        hover
        :no-border-collapse="true"
        :fields="fields"
        :items="openCourses"
      >
        <template v-slot:cell(name)="data">
          <a
            href=""
            @click.prevent="initEnterOpenCourseAsAnonymousUser(data.item.id)"
          >
            {{ data.item.name }}
          </a>
        </template>
        <template v-slot:cell(discipline_id)="data">
          <div v-if="!isAdmin">
            {{ data.item.discipline_name ? data.item.discipline_name : 'None specified' }}
          </div>
          <div v-if="isAdmin">
            <DisciplineSelect :key="`discipline-${disciplineCache}`"
                              :current-discipline-id="data.item.discipline_id ? data.item.discipline_id : null"
                              :discipline-options="disciplineOptions"
                              :course-id="data.item.id"
                              @reload="reload"
            />
          </div>
        </template>
        <template v-slot:cell(description)="data">
          <div class="truncate" :title="data.item.description">
            {{ data.item.description }}
          </div>
        </template>
        <template v-slot:cell(actions)="data">
          <div class="mb-0">
            <b-tooltip :target="getTooltipTarget('viewAssignments',data.item.id)"
                       triggers="hover"
                       delay="500"
            >
              View assignments for {{ data.item.name }}
            </b-tooltip>
            <a :id="getTooltipTarget('viewAssignments',data.item.id)"
               href="#"
               class="pr-1"
               @click="openAssignmentsModal(data.item.id)"
            >
              <b-icon class="text-muted"
                      icon="eye"
                      :aria-label="`View ${data.item.name} Assignments`"
              />
            </a>
            <ImportCourse v-if="user && user.role === 2"
                          :one-button-per-row="oneButtonPerRow"
                          :open-course="data.item"
                          :icon="true"
            />
          </div>
        </template>
      </b-table>
    </div>
    <b-alert variant="info" :show="!openCourses.length && !isLoading">
      There are no courses which match your criteria.
    </b-alert>
  </div>
</template>

<script>
import Autocomplete from '@trevoreyre/autocomplete-vue'
import '@trevoreyre/autocomplete-vue/dist/style.css'
import { mapGetters } from 'vuex'
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import Form from 'vform'
import Email from '~/components/Email'
import ImportCourse from '~/components/ImportCourse'
import { logout } from '~/helpers/Logout'
import { getTooltipTarget, initTooltips } from '~/helpers/Tooptips'
import DisciplineSelect from '../components/DisciplineSelect.vue'
import {
  getDisciplines,
  initEditDiscipline,
  initDeleteDiscipline,
  saveDiscipline,
  deleteDiscipline, resetDiscipline
} from '../helpers/Disciplines'


export default {
  components: {
    DisciplineSelect,
    Loading,
    Email,
    ImportCourse,
    Autocomplete
  },
  metaInfo () {
    return { title: 'Commons' }
  },
  data: () => ({
    disciplineCache: 0,
    disciplineForm: new Form({
      name: ''
    }),
    activeDiscipline: {},
    discipline: null,
    disciplineOptions: [{ value: null, text: 'Choose a discipline' }],
    description: '',
    school: '',
    name: '',
    schools: [],
    authors: '',
    type: '',
    title: '',
    oneButtonPerRow: false,
    oneCoursePerRow: false,
    loggingIn: true,
    isLoading: true,
    openCourses: [],
    originalOpenCourses: [],
    assignments: [],
    openCourseId: 0,
    loginForm: new Form({
      username: '',
      password: ''
    }),
    openCourseName: '',
    fields: []
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    })
  },
  created () {
    this.logout = logout
    this.isAdmin = this.user && window.config.isAdmin
  },
  mounted () {
    this.getTooltipTarget = getTooltipTarget
    initTooltips(this)
    this.resizeHandler()
    window.addEventListener('resize', this.resizeHandler)
    this.type = this.$route.params.type
    if (!['commons', 'public'].includes(this.type)) {
      this.$noty.error(`The type should be either commons or public: ${this.type} is not a valid type.`)
      return false
    }
    this.title = this.type === 'commons' ? 'Commons' : 'Public Courses'
    this.getOpenCourses()
    this.getDisciplines()
  },
  beforeDestroy () {
    window.removeEventListener('resize', this.resizeHandler)
  },
  methods: {
    logout,
    initEditDiscipline,
    initDeleteDiscipline,
    saveDiscipline,
    deleteDiscipline,
    getDisciplines,
    resetDiscipline,
    beginLogin () {
      window.location.href = '/api/oidc/initiate-login/web'
    },
    reload () {
      this.getOpenCourses()
      this.getDisciplines()
    },
    reset () {
      this.$refs.authorSearch.value = ''
      this.$refs.schoolSearch.value = ''
      this.school = this.name = this.author = this.description = ''
      this.discipline = null
      this.openCourses = this.originalOpenCourses
    },
    update () {
      this.openCourses = this.originalOpenCourses
      if (this.school) {
        this.openCourses = this.openCourses.filter(item => item.school === this.school)
      }
      if (this.author) {
        this.openCourses = this.openCourses.filter(item => item.instructor.toLowerCase().includes(this.author.toLowerCase()))
      }
      if (this.name) {
        this.openCourses = this.openCourses.filter(item => item.name.toLowerCase().includes(this.name.toLowerCase()))
      }
      if (this.discipline) {
        this.openCourses = this.openCourses.filter(item => item.discipline_id === this.discipline)
        this.disciplineCache++
      }
      if (this.description) {
        this.openCourses = this.openCourses.filter(item => item.description.toLowerCase().includes(this.description.toLowerCase()))
      }
    },
    selectAuthor (selectedAuthor) {
      this.author = selectedAuthor
    },
    searchByAuthor (input) {
      if (input.length < 1) {
        return []
      }
      let matches = this.authors.filter(authors => authors.toLowerCase().includes(input.toLowerCase()))
      let authors = []
      if (matches) {
        for (let i = 0; i < matches.length; i++) {
          authors.push(matches[i])
        }
        authors.sort()
      }
      return authors
    },
    selectSchool (selectedSchool) {
      this.school = selectedSchool
    },
    searchBySchool (input) {
      if (input.length < 1) {
        return []
      }
      let matches = this.schools.filter(school => school.toLowerCase().includes(input.toLowerCase()))
      let schools = []
      if (matches) {
        for (let i = 0; i < matches.length; i++) {
          schools.push(matches[i])
        }
        schools.sort()
      }
      return schools
    },
    resizeHandler () {
      this.oneCoursePerRow = this.zoomGreaterThan(1.2)
      this.oneButtonPerRow = this.zoomGreaterThan(1)
    },
    async initEnterOpenCourseAsAnonymousUser (courseId) {
      if (this.user && this.user.role === 2) {
        this.isLoading = true
        try {
          const { data } = await axios.post('/api/users/set-anonymous-user-session')
          if (data.type === 'error') {
            this.$noty.error(data.message)
            return false
          }
          await this.$router.push(`/students/courses/${courseId}/assignments/anonymous-user`)
        } catch (error) {
          this.$noty.error(error.message)
        }
      } else {
        this.$bvModal.show('modal-enter-course')
      }
    },
    async openAssignmentsModal (courseId) {
      try {
        const { data } = await axios.get(`/api/assignments/open/${this.type}/${courseId}`)
        if (data.type !== 'success') {
          this.$noty[data.type](data.message)
          return false
        }
        this.assignments = data.assignments
        this.$bvModal.show('modal-assignments')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getOpenCourses () {
      try {
        const { data } = await axios.get(`/api/courses/${this.type}`)
        if (data.type !== 'success') {
          this.isLoading = false
          this.$noty[data.type](data.message)
          return false
        }
        if (this.type === 'commons') {
          for (let i = 0; i < data.commons_courses.length; i++) {
            if (!data.commons_courses[i].description) {
              data.commons_courses[i].description = 'None provided.'
            }
          }
        }
        this.openCourses = this.type === 'commons' ? data.commons_courses : data.public_courses
        this.isLoading = false
        this.originalOpenCourses = this.openCourses
        this.fields = [
          {
            key: 'discipline_id',
            label: 'Discipline',
            sortable: true
          },
          {
            key: 'name',
            label: 'Title',
            sortable: true
          }
        ]
        if (this.type === 'commons') {
          this.fields.push({
            key: 'description',
            label: 'Description'
          })
        } else {
          this.schools = []
          this.authors = []
          for (let i = 0; i < data.public_courses.length; i++) {
            if (!(this.schools).includes(data.public_courses[i].school)) {
              this.schools.push(data.public_courses[i].school)
            }
            if (!(this.authors).includes(data.public_courses[i].instructor)) {
              this.authors.push(data.public_courses[i].instructor)
            }
          }
          this.fields.push({
            key: 'instructor',
            label: 'Author',
            sortable: true
          })
          this.fields.push(
            {
              key: 'school',
              sortable: true
            })
        }
        this.fields.push('actions')
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>
<style>
.truncate {
  max-width: 600px; /* Adjust the width as needed */
}
</style>
