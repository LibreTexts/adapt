<template>
  <div>
    <PageTitle v-if="canViewScores" title="Gradebook" />
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-if="hasAssignments">
        <div v-if="canViewScores">
          <b-container>
            <b-modal id="modal-grading-information"
                     title="Grading Information"
                     hide-footer
            >
              <p>
                To compute the weighted averages, we first compute the percent score on each assignment, then take a
                straight average of all assignments within an assignment group. The averages by assignment
                group are weighted by the
                <span><router-link
                  :to="{name: 'course_properties.letter_grades', params: { courseId: courseId }}"
                >
                  assignment group weights</router-link></span> which determine the
                <router-link :to="{name: 'course_properties.letter_grades', params: { courseId: courseId }}">
                  letter grades
                </router-link>
                for the course. Marked assignments (<span style="font-size: 12px;color:red">*</span>) are not included
                in the score computation.
              </p>
              <p>
                If you prefer a different grading methodology, please download the scores and input them into a
                spreadsheet.
              </p>
              <ul>
                <li>
                  Click on any student name to log in as them and get a better understanding of that student's
                  performance
                </li>
                <li>
                  Click on any item in the Gradebook if you need to enter a score override
                </li>
              </ul>
            </b-modal>
            <b-row>
              <span class="pr-2">Assignment View</span>
              <toggle-button
                :width="100"
                :value="assignmentView === 'individual'"
                :sync="true"
                :font-size="14"
                :margin="4"
                :color="toggleColors"
                :labels="{checked: 'Individual', unchecked: 'By Group'}"
                @change="changeAssignmentView()"
              />
            </b-row>
            <b-row v-if="assignmentView === 'individual'">
              <TimeSpent @updateView="showTimeSpentOption" />
            </b-row>
            <b-row>
              <span v-if="user.id === 5">
                <span>FERPA Mode: </span>
                <toggle-button
                  class="mt-2 mr-2"
                  :width="55"
                  :value="ferpaMode"
                  :sync="true"
                  :font-size="14"
                  :margin="4"
                  :color="toggleColors"
                  :labels="{checked: 'On', unchecked: 'Off'}"
                  @change="submitFerpaMode()"
                />
                <br>
              </span>
              <span>
                <span v-if="assignmentView === 'individual'">
                  <b-button variant="primary" size="sm" class="mr-2"
                            @click="$bvModal.show('modal-grading-information')"
                  >
                    Grading Information
                  </b-button>
                </span>
                <span v-if="assignmentView === 'individual'">
                  <span v-show="user.role ===2">
                    <b-button variant="info" size="sm" class="mr-2"
                              @click="openOverrideAssignmentScoresModal"
                    >
                      Override Assignment Scores
                    </b-button>
                  </span>
                  <a class="float-right mb-2 btn-sm btn-primary link-outline-primary-btn"
                     :href="`/api/scores/${courseId}/${sectionId}/1`"
                  >
                    Download Scores
                  </a>
                </span>
              </span>
            </b-row>
            <b-row v-if="hasMultipleSections" class="mb-2">
              <span class="mt-1">Section View</span>
              <b-col lg="3">
                <b-form-select
                  id="section_view"
                  v-model="sectionId"
                  title="Section View"
                  :options="sections"
                  @change="getScores"
                />
              </b-col>
            </b-row>
            <b-row class="mb-3 d-inline-flex">
              <autocomplete
                ref="studentFilter"
                style="width:250px"
                placeholder="Filter by student"
                aria-label="Filter by student"
                :search="searchByStudent"
                @submit="filterBySelectedStudent"
              />
              <span class="mt-1 mr-2 ml-2"><b-button size="sm"
                                                     @click="items=originalItems;assignmentGroupItems = originalAssignmentGroupItems"
              >Reset Students</b-button></span>

              <autocomplete
                v-if="assignmentView === 'individual'"
                ref="assignmentFilter"
                style="width:500px"
                placeholder="Filter by assignment"
                aria-label="Filter by assignment"
                :search="searchByAssignment"
                @submit="filterBySelectedAssignment"
              />
              <span v-if="assignmentView === 'individual'" class="mt-1 mb-1 mr-2 ml-2"><b-button size="sm"
                                                                                                 @click="fields=originalFields"
              >Reset Assignments</b-button></span>
            </b-row>
            <b-row v-if="assignmentView === 'individual' && hasOutOfRangeScores" class="p-2 small">
              <span class="score-out-of-range mr-1">Highlighted</span> assignment scores are above the
              assignment's points or below 0, usually from an override. Hover over one for details.
            </b-row>
            <b-row v-if="assignmentView !== 'individual'" class="p-2">
              Assignments that are not included in the final weighted
              average (<span
                class="text-danger"
              >*</span>) are not included below.
            </b-row>
            <b-row>
              <b-table v-show="assignmentView === 'by group'"
                       aria-label="Assignment group gradebook view"
                       striped
                       hover
                       responsive="true"
                       :no-border-collapse="true"
                       :items="assignmentGroupItems"
                       :fields="assignmentGroupFields"
                       :sticky-header="tableHeight"
                       sort-icon-left
              >
                <template v-for="field in assignmentGroupFields" v-slot:[`head(${field.key})`]="data">
                  <span :key="field.key" v-html="field.label" />
                </template>
                <template v-slot:cell(name)="data">
                  <a href=""
                     @click.prevent="loginAsStudentInCourse(data.item.user_id)"
                  >
                    {{ data.item.name }}
                  </a>
                </template>
              </b-table>

              <b-table v-show="assignmentView === 'individual'"
                       aria-label="Individual assignment gradebook view"
                       striped
                       hover
                       responsive="true"
                       :no-border-collapse="true"
                       :items="items"
                       :fields="fields"
                       :sort-by.sync="sortBy"
                       primary-key="userId"
                       :sort-desc.sync="sortDesc"
                       :sticky-header="tableHeight"
                       sort-icon-left
              >
                <template v-for="field in fields" v-slot:[`head(${field.key})`]="data">
                  <div :key="`assignment-${field.assignment_id}`">
                    <span v-if="field.label">
                      {{ field.label }}
                    </span>
                    <div v-if="!field.label" class="text-center">
                      <a :href="`/instructors/assignments/${field.assignment_id}/information/questions`">{{ field.name_only }}</a><br>
                      <span style="font-size: 12px">
                        ({{ field.points }} points)</span>
                      <span v-show="field.not_included"
                            :id="`not-included-tooltip-${field.assignment_id}`"
                            style="font-size: 12px;"
                            class="text-danger"
                      >
                        *
                      </span>
                      <br>
                      <span style="font-size: 12px;">&mu;<sub
                        v-if="showMeanAssignmentOnTask ||showMeanAssignmentInReview"
                      >scores</sub>: {{
                        field.mean
                      }}
                      </span>
                      <span v-if="showMeanAssignmentOnTask">
                        <br>
                        <span style="font-size: 12px;">&mu;<sub>on-task</sub>: {{
                          getMeanAssignmentTimeSpent(meanAssignmentTimeOnTasks, field.assignment_id)
                        }}</span>
                      </span>
                      <span v-if="showMeanAssignmentInReview">
                        <br>
                        <span style="font-size: 12px;">&mu;<sub>in-review</sub>:
                          {{
                            getMeanAssignmentTimeSpent(meanAssignmentTimeInReviews, field.assignment_id)
                          }}</span>
                        <span v-if="meanAssignmentTimeInReviews.find(item =>item.id===field.assignment_id)">
                          <br>
                          <span
                            style="font-size: 12px;"
                          >n<sub>in-review</sub>:
                            {{
                              meanAssignmentTimeInReviews.find(item => item.id === field.assignment_id).num_in_review
                            }}</span>
                        </span>
                      </span>
                      <b-tooltip :target="`not-included-tooltip-${field.assignment_id}`"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        {{ field.name_only }} will not be included when computing your final weighted average.
                      </b-tooltip>
                    </div>
                  </div>
                </template>
                <template v-slot:cell()="data">
                  <span v-if="['name'].includes(data.field.key)">
                    <a href=""
                       @click.prevent="getStudentAction(data.value,data.item.userId, data.field.key, data.item.name)"
                    >
                      {{ data.value }}
                    </a>
                  </span>
                  <span v-if="!['name'].includes(data.field.key)"
                        @click="getStudentAction(data.value,data.item.userId, data.field.key, data.item.name)"
                  ><span :class="{ 'score-out-of-range': outOfRangeMessage(data.value, data.field.points) }"
                         :title="outOfRangeMessage(data.value, data.field.points)"
                  >{{ data.value }}</span> <span v-if="showAssignmentTimeSpent">{{
                    getAssignmentTimeSpent(data.item.userId, data.field.key, timeSpentArr)
                  }}</span>
                  </span>
                </template>
              </b-table>
            </b-row>
          </b-container>
        </div>
      </div>
      <div v-else>
        <b-alert v-if="!isLoading" show variant="warning">
          <a href="#" class="alert-link">You have no assignments or students yet.</a>
        </b-alert>
      </div>
    </div>
    <b-modal
      id="modal-update-extra-credit"
      ref="modal"
      title="Update Extra Credit"
      ok-title="Submit"
      @ok="submitUpdateExtraCredit"
      @hidden="resetModalForms"
    >
      <p>
        Extra Credit is applied after the final weighted average is computed. As an example, if the final weighted
        average is 82% and you give your student extra credit of 5%, their final average will be 87%.
      </p>
      <b-form ref="form">
        <b-form-group
          id="extra_credit"
          label-cols-sm="4"
          label-cols-lg="3"
          label="Extra Credit"
          label-for="Extra Credit"
        >
          <b-form-row>
            <b-col lg="4">
              <b-form-input
                id="score"
                v-model="extraCreditForm.extra_credit"
                type="text"
                placeholder=""
                :class="{ 'is-invalid': extraCreditForm.errors.has('extra_credit') }"
                @keydown="extraCreditForm.errors.clear('extra_credit')"
              />
              <has-error :form="extraCreditForm" field="extra_credit" />
            </b-col>
          </b-form-row>
        </b-form-group>
      </b-form>
    </b-modal>
    <b-modal id="modal-confirm-override-assignment-scores"
             title="Confirm override assignment scores"
    >
      <p>
        I have saved a copy of the current scores to my local computer. I understand that ADAPT cannot retrieve
        any of my past scores.
      </p>
      <p>Would you like ADAPT to override your scores for <strong>{{ assignmentName }}</strong>?</p>

      <template #modal-footer="{ cancel, ok }">
        <b-button size="sm" variant="danger" @click="$bvModal.hide('modal-confirm-override-assignment-scores')">
          Cancel
        </b-button>
        <b-button size="sm" variant="success"
                  @click="submitOverrideAssignmentScores()"
        >
          Let's do it!
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-override-assignment-scores"
             ref="modal"
             title="Override assignment scores"
             size="lg"
    >
      <span class="font-weight-bold mr-2">
        Step 1: Download Current Gradebook Spreadsheet</span>
      <a class="float-right mb-2 btn-sm btn-primary link-outline-primary-btn"
         :href="`/api/scores/${courseId}/${sectionId}/1`"
         @click="downloadedCurrentGradeBookSpreadsheet = true"
      >
        Download Scores
      </a>
      <div v-show="downloadedCurrentGradeBookSpreadsheet">
        <p class="font-weight-bold">
          Step 2: Choose an assignment and download the Assignment Scores Template.
        </p>
        <b-form ref="form">
          <b-form-row class="mb-2">
            <b-col lg="5">
              <b-form-select v-model="assignmentId"
                             :options="assignmentOptions"
                             @change="updateAssignmentName"
              />
            </b-col>
            <b-col>
              <a v-if="assignmentId"
                 class="float-right mb-2 btn-sm btn-primary link-outline-primary-btn"
                 :href="`/api/assignments/download-users-for-assignment-override/${assignmentId}`"
                 @click="downloadedAssignmentUsers= true"
              >
                Download
              </a>
              <a v-if="!assignmentId"
                 class="float-right mb-2 btn-sm btn-primary link-outline-primary-btn"
                 @click.prevent="showChooseAnAssignmentMessage()"
              >
                Download
              </a>
            </b-col>
          </b-form-row>
          <b-container v-show="downloadedAssignmentUsers">
            <b-row>
              <p class="font-weight-bold">
                Step 3: Upload the Assigment Scores Template. Blank and dashed cells will be ignored.
              </p>
              <b-form-file
                ref="assignmentOverrideScores"
                v-model="assignmentOverrideScoresFileForm.overrideScoresFile"
                class="mb-2"
                placeholder="Choose a file or drop it here..."
                drop-placeholder="Drop file here..."
              />
              <div v-if="uploading">
                <b-spinner small type="grow" />
                Uploading file...
              </div>
              <input type="hidden" class="form-control is-invalid">
              <div class="help-block invalid-feedback">
                {{ assignmentOverrideScoresFileForm.errors.get('overrideScoresFile') }}
              </div>
            </b-row>
            <b-row align-h="end">
              <b-button variant="info" size="sm"
                        :disabled="assignmentOverrideScoresFileForm.overrideScoresFile.length === 0"
                        @click="handleOk"
              >
                Upload scores
              </b-button>
            </b-row>
          </b-container>
          <b-container v-show="fromToScores.length">
            <b-row>
              <p class="font-weight-bold">
                Step 3: Review your overrides and confirm.
                <b-button variant="primary" size="sm" @click="openConfirmOverrideAssignmentScoresModal">
                  Confirm
                </b-button>
              </p>
              <b-table
                aria-label="Override assignment scores"
                striped
                hover
                :no-border-collapse="true"
                :fields="fromToFields"
                :items="fromToScores"
              />
            </b-row>
          </b-container>
        </b-form>
      </div>
      <template #modal-footer>
        <b-container>
          <b-button
            variant="secondary"
            size="sm"
            class="float-right"
            @click="$bvModal.hide('modal-override-assignment-scores')"
          >
            Close
          </b-button>
        </b-container>
      </template>
    </b-modal>
    <AssignmentOverrideScore :assignment-id="parseInt(assignmentId)"
                             :assignment-name="assignmentName"
                             :student-user-id="studentUserId"
                             :student-name="studentName"
                             :form="form"
    />
  </div>
</template>
<script>
import axios from 'axios'
import Form from 'vform'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { loginAsStudentInCourse } from '~/helpers/LoginAsStudentInCourse'
import { mapGetters } from 'vuex'
import AssignmentOverrideScore from '~/components/AssignmentOverrideScore'
import { ToggleButton } from 'vue-js-toggle-button'
import Autocomplete from '@trevoreyre/autocomplete-vue'
import '@trevoreyre/autocomplete-vue/dist/style.css'
import $ from 'jquery'
import TimeSpent from '~/components/TimeSpent'
// get all students enrolled in the course: course_enrollment
// get all assignments for the course
//
export default {
  components: {
    Autocomplete,
    AssignmentOverrideScore,
    Loading,
    ToggleButton,
    TimeSpent
  },
  metaInfo () {
    return { title: 'Gradebook' }
  },
  middleware: 'auth',
  data: () => ({
    showMeanAssignmentOnTask: false,
    showMeanAssignmentInReview: false,
    meanAssignmentTimeOnTasks: [],
    meanAssignmentTimeInReviews: [],
    showAssignmentTimeSpent: false,
    timeSpentArr: [],
    timeSpent: 'hidden',
    timeSpentOptions: [
      { value: 'hidden', text: 'Hidden' },
      { value: 'on_task', text: 'On Task' },
      { value: 'in_review', text: 'In Review' }
    ],
    showAssignmentTimeOnTask: false,
    assignmentTimeOnTasks: [],
    assignmentTimeInReviews: [],
    assignmentFields: [],
    originalFields: [],
    originalAssignmentGroupItems: [],
    originalItems: [],
    selectedStudent: '',
    studentQuery: '',
    selectedAssignment: '',
    assignmentQuery: '',
    assignmentGroupItems: [],
    assignmentGroupFields: [],
    assignmentView: 'individual',
    assignmentGroupId: null,
    toggleColors: window.config.toggleColors,
    ferpaMode: false,
    form: new Form({
      score: null
    }),
    downloadedCurrentGradeBookSpreadsheet: false,
    downloadedAssignmentUsers: false,
    assignmentName: '',
    fromToScores: [],
    fromToFields: [
      {
        key: 'name',
        sortable: true
      },
      {
        key: 'current_score',
        sortable: true
      },
      {
        key: 'override_score',
        sortable: true
      }
    ],
    uploading: false,
    assignmentOverrideScoresFileForm: new Form({
      overrideScoresFile: []
    }),
    assignmentOverrideScoresForm: new Form({
      overrideScores: []
    }),
    downloadAssignmentUsers: [],
    assignmentOptions: [],
    studentName: '',
    sections: [{ text: 'All Sections', value: 0 }],
    hasMultipleSections: false,
    sectionId: 0,
    weightedAverageAssignmentId: 0,
    extraCreditAssignmentId: 0,
    isLoading: true,
    extraCreditForm: new Form({
      extra_credit: null,
      student_user_id: 0,
      course_id: 0
    }),
    sortBy: 'name',
    sortDesc: false,
    courseId: '',
    fields: [],
    scores: [],
    items: [],
    hasAssignments: false,
    studentUserId: 0,
    assignmentId: 0,
    assignmentsArray: [],
    canViewScores: false,
    currentScore: null,
    tableHeight: '0px'
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    hasOutOfRangeScores () {
      return this.items.some(item => this.fields.some(field => this.outOfRangeMessage(item[field.key], field.points)))
    },
    isAdmin: () => window.config.isAdmin
  },
  watch: {
    studentQuery: function (student) {
      if (!student.length) {
        this.items = this.originalItems
        this.assignmentGroupItems = this.originalAssignmentGroupItems
      }
    },
    assignmentQuery: function (assignment) {
      if (!assignment.length) {
        this.fields = this.originalFields
      }
    }
  },
  updated: function () {
    this.$nextTick(function () {
      $('.autocomplete-input').on('click', function () {
        // fix needed since it was going behind the table
        $('ul[id^="autocomplete-result-list"]').attr('style', 'position: absolute; z-index: 100; width: 100%; visibility: hidden; pointer-events: none; top: 100%;')
      }).attr('style', 'padding:5px 48px')
    })
  },
  mounted () {
    this.loginAsStudentInCourse = loginAsStudentInCourse
    this.courseId = this.$route.params.courseId
    this.isLoading = true
    if (this.isAdmin) {
      this.getFerpaMode()
    }
    this.initGetScores()
    this.fixTableHeight()
  },
  methods: {
    // Only assignment columns have points; a cell can be "-" or text when there's no score. Returns a message for a
    // score above the assignment's points or below 0, and '' otherwise.
    outOfRangeMessage (value, maximum) {
      if (maximum === undefined || maximum === null || maximum === '' || value === undefined || value === null) {
        return ''
      }
      const score = parseFloat(String(value).trim())
      const max = parseFloat(maximum)
      if (isNaN(score) || isNaN(max)) {
        return ''
      }
      if (score > max + 0.005) {
        return `Above the maximum of ${max} ${max === 1 ? 'point' : 'points'}`
      }
      if (score < -0.005) {
        return 'Below 0'
      }
      return ''
    },
    getMeanAssignmentTimeSpent (object, assignmentId) {
      let timeSpent = object.find(item => item.id === assignmentId)
      return timeSpent ? timeSpent.mean_time_spent : 'N/A'
    },
    showTimeSpentOption (option) {
      this.showAssignmentTimeSpent = true
      this.showMeanAssignmentInReview = false
      this.showMeanAssignmentOnTask = false
      switch (option) {
        case ('on_task'):
          this.showMeanAssignmentOnTask = true
          this.timeSpentArr = this.assignmentTimeOnTasks
          break
        case ('in_review'):
          this.showMeanAssignmentInReview = true
          this.timeSpentArr = this.assignmentTimeInReviews
          break
        default:
          this.showAssignmentTimeSpent = false
          break
      }
    },
    getAssignmentTimeSpent (userId, assignmentId, timeSpentArr) {
      let assignmentTimeSpent = timeSpentArr.find(timeSpent => (parseInt(timeSpent.user_id) === parseInt(userId) && parseInt(timeSpent.assignment_id) === parseInt(assignmentId)))
      return assignmentTimeSpent ? assignmentTimeSpent.time_spent
        : ''
    },
    showChooseAnAssignmentMessage () {
      this.$noty.info('Please choose an assignment.')
    },
    searchByStudent (input) {
      if (input.length < 1) {
        return []
      }
      let matches = this.originalItems.filter(student => student.name.toLowerCase().includes(input.toLowerCase()))
      let students = []
      if (matches) {
        for (let i = 0; i < matches.length; i++) {
          students.push(matches[i].name)
        }
        students.sort()
      }
      console.log(students)
      return students
    },
    searchByAssignment (input) {
      if (input.length < 1) {
        return []
      }
      console.log(this.assignmentFields)
      let matches = this.assignmentFields.filter(assignment => assignment.name_only.toLowerCase().includes(input.toLowerCase()))
      let assignments = []
      if (matches) {
        for (let i = 0; i < matches.length; i++) {
          assignments.push(matches[i].name_only)
        }
        assignments.sort()
      }
      console.log(assignments)
      return assignments
    },
    filterBySelectedStudent (selectedStudent) {
      this.items = this.originalItems
      this.items = this.items.filter(student => student.name === selectedStudent)
      this.assignmentGroupItems = this.assignmentGroupItems.filter(student => student.name === selectedStudent)
      this.$refs.studentFilter.value = ''
    },
    filterBySelectedAssignment (selectedAssignment) {
      this.fields = this.originalFields
      this.fields = this.originalFields.filter(field => ['name', 'email'].includes(field.key))
      this.fields.push(this.assignmentFields.find(assignment => assignment.name_only === selectedAssignment))
      this.$refs.assignmentFilter.value = ''
    },
    resetStudentFilter () {
      this.items = this.originalItems
      this.assignmentGroupItems = this.originalAssignmentGroupItems
      this.selectedStudent = ''
      this.$refs.studentSearch.inputValue = ''
    },
    resetAssignmentFilter () {
      this.fields = this.originalFields
      this.selectedAssignment = ''
      this.$refs.assignmentSearch.inputValue = ''
    },
    changeAssignmentView () {
      this.assignmentView = this.assignmentView === 'individual' ? 'by group' : 'individual'
    },
    fixTableHeight () {
      this.tableHeight = (window.screen.height - 200) + 'px'
    },
    async initGetScores () {
      try {
        await this.getScores()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getFerpaMode () {
      try {
        const { data } = await axios.get(`/api/scores/get-ferpa-mode`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.ferpaMode = Boolean(data.ferpa_mode)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitFerpaMode () {
      try {
        const { data } = await axios.patch(`/api/cookie/set-ferpa-mode/${+this.ferpaMode}`)
        if (data.type === 'success') {
          this.isLoading = true
          this.ferpaMode = !this.ferpaMode
          await this.getScores()
          this.isLoading = false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateScore (assignmentId, studentUserId, cellContents) {
      this.form.score = null
      this.form.errors.clear()
      await this.getScores()
    },
    getAssignmentNameAsFile () {
      return this.assignmentName.replace(/[/\\?%*:|"<>]/g, '-') + '.csv'
    },
    updateAssignmentName (target) {
      let assignment = this.assignmentOptions.filter(e => e.value === target)[0]
      this.assignmentName = assignment.text
    },
    async submitOverrideAssignmentScores () {
      try {
        this.assignmentOverrideScoresForm.overrideScores = this.fromToScores
        const { data } = await this.assignmentOverrideScoresForm.patch(`/api/scores/override-scores/${this.assignmentId}`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.isLoading = true
          this.$bvModal.hide('modal-override-assignment-scores')
          this.$bvModal.hide('modal-confirm-override-assignment-scores')
          await this.getScores()
          this.isLoading = false
        } else {
          this.$noty[data.type](data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
        this.$bvModal.hide('modal-override-assignment-scores')
        this.$bvModal.hide('modal-confirm-override-assignment-scores')
      }
    },
    openConfirmOverrideAssignmentScoresModal () {
      this.$bvModal.show('modal-confirm-override-assignment-scores')
    },
    async handleOk (bvModalEvt) {
      bvModalEvt.preventDefault()
      try {
        if (this.uploading) {
          this.$noty.info('Please be patient while the file is uploading.')
          return false
        }
        // this.fileFeedbackForm.errors.set('assignmentScoresFile', null)
        this.uploading = true
        // https://stackoverflow.com/questions/49328956/file-upload-with-vue-and-laravel
        let formData = new FormData()
        formData.append('overrideScoresFile', this.assignmentOverrideScoresFileForm.overrideScoresFile)
        formData.append('_method', 'put') // add this
        const { data } = await axios.post(`/api/scores/${this.assignmentId}/upload-override-scores`, formData)
        if (data.type === 'error') {
          if (data.override_score_errors) {
            let badStudents = data.override_score_errors.join(', ')
            let badStudentsMessage = 'The following students have scores which are not positive numbers: ' + badStudents
            this.assignmentOverrideScoresFileForm.errors.set('overrideScoresFile', badStudentsMessage)
            this.assignmentOverrideScoresFileForm.overrideScoresFile = []
          }
          if (data.message) {
            this.$noty.error(data.message)
          }
        } else {
          this.fromToScores = data.from_to_scores
        }
      } catch (error) {
        if (error.message.includes('status code 413')) {
          error.message = 'The maximum size allowed is 10MB.'
        }
        this.$noty.error(error.message)
      }
      this.uploading = false
    },
    async openOverrideAssignmentScoresModal () {
      this.downloadedCurrentGradeBookSpreadsheet = false
      try {
        const { data } = await axios.get(`/api/assignments/options/${this.courseId}`)
        console.log(data)
        if (data.type !== 'success') {
          this.$noty[data.type](data.message)
          return false
        }
        this.assignmentOptions = data.assignments
        this.fromToScores = []

        this.downloadedAssignmentUsers = false
        this.assignmentOverrideScoresFileForm.overrideScoresFile = []
        this.assignmentId = 0

        this.$bvModal.show('modal-override-assignment-scores')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    resetAll (modalId) {
      this.resetModalForms()
      // Hide the modal manually
      this.$nextTick(() => {
        this.$bvModal.hide(modalId)
      })
    },
    resetModalForms () {
      this.extraCreditForm.extra_credit = ''
      this.extraCreditForm.errors.clear()
    },
    initStudentAssignmentCell (key) {
      console.log(key)
      return `cell(${key})` // simple string interpolation
    },
    async getScoreByAssignmentAndStudent () {
      const { data } = await axios.get(`/api/scores/assignment-user/${this.assignmentId}/${this.studentUserId}`)
      console.log(data)
      if (data.type === 'success') {
        this.currentScore = data.score
        this.form.score = data.score
        this.assignmentName = data.assignment_name
      } else {
        this.$noty.error(data.message)
        return false
      }
    },
    async getStudentAction (value, studentUserId, assignmentId, studentName) {
      // name shouldn't be clickable

      if (parseInt(assignmentId) === parseInt(this.weightedAverageAssignmentId)) {
        return false
      }
      if (assignmentId === 'email') {
        return false
      }
      if (assignmentId === 'name') {
        this.loginAsStudentInCourse(studentUserId)
      } else {
        this.studentUserId = studentUserId
        this.studentName = studentName
        if (parseInt(assignmentId) === parseInt(this.extraCreditAssignmentId)) {
          await this.openExtraCreditModal()
          return false
        }
        await this.openScoreOverrideModal(assignmentId)
      }
    },
    async openExtraCreditModal () {
      try {
        this.extraCreditForm.course_id = this.courseId
        this.extraCreditForm.student_user_id = this.studentUserId
        const { data } = await axios.get(`/api/extra-credit/${this.courseId}/${this.studentUserId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.extraCreditForm.extra_credit = data.extra_credit
        this.$bvModal.show('modal-update-extra-credit')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitUpdateExtraCredit (bvModalEvt) {
      bvModalEvt.preventDefault()
      try {
        const { data } = await this.extraCreditForm.post(`/api/extra-credit`)
        console.log(data)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          await this.getScores()
          this.resetAll('modal-update-extra-credit')
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    async openScoreOverrideModal (assignmentId) {
      this.assignmentId = assignmentId
      try {
        this.isLoading = true
        await this.getScoreByAssignmentAndStudent()
        this.isLoading = false
        this.$bvModal.show('modal-student-override-score')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getScores () {
      try {
        const { data } = await axios.get(`/api/scores/${this.courseId}/${this.sectionId}/0`)
        this.isLoading = false
        console.log(data)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }

        if (data.hasAssignments) {
          if (this.sections.length === 1) {
            let sections = data.sections
            this.hasMultipleSections = sections.length > 1

            if (this.hasMultipleSections) {
              for (let i = 0; i < sections.length; i++) {
                let section = sections[i]
                this.sections.push({ text: section.name, value: section.id })
              }
            }
          }
          this.originalItems = this.items = data.table.rows
          this.assignmentTimeOnTasks = data.assignment_time_on_tasks
          this.meanAssignmentTimeOnTasks = data.mean_assignment_time_on_tasks

          this.assignmentTimeInReviews = data.assignment_time_in_reviews
          this.meanAssignmentTimeInReviews = data.mean_assignment_time_in_reviews
          // console.log(this.items)
          this.fields = data.table.fields // Name
          // console.log(this.fields)
          // map the group_ids to specific colors¬
          // do the headers
          let assignmentGroups = []
          for (let i = 0; i < data.assignment_groups.length; i++) {
            assignmentGroups.push(data.assignment_groups[i].assignments)
          }
          this.originalAssignmentGroupItems = this.assignmentGroupItems = data.score_info_by_assignment_group
          for (let i = 2; i < this.fields.length - 3; i++) {
            let key = this.fields[i]['key']
            this.fields[i]['thStyle'] = this.getHeaderColor(key, assignmentGroups)
          }

          for (let i = this.fields.length - 3; i < this.fields.length; i++) {
            this.fields[i]['thStyle'] = { 'align': 'center', 'min-width': '100px' }
          }
          this.originalFields = this.fields
          this.assignmentFields = this.fields.filter(field => !['name', 'email'].includes(field.key))
          this.assignmentGroupFields = [
            {
              key: 'name',
              label: 'Name',
              isRowHeader: true,
              sortable: true,
              stickyColumn: true
            },
            {
              key: 'email',
              label: 'Email',
              sortable: true,
              stickyColumn: true
            }]

          // get the colors from one of the assignments
          for (let i = 0; i < data.assignment_groups.length; i++) {
            let assignmentGroupField = {
              key: data.assignment_groups[i].assignment_group,
              label: `${data.assignment_groups[i].assignment_group}<br><span style="font-size: 12px">(${data.assignment_groups[i].total_points} points)</span>`,
              sortable: true,
              thClass: 'text-center',
              tdClass: 'text-center'
            }
            if (data.assignment_groups[i].assignments.length) {
              let assignment = data.assignment_groups[i].assignments[0]
              let assignmentWithinGroup = this.fields.find(field => parseInt(field.key) === parseInt(assignment))
              if (assignmentWithinGroup) {
                assignmentGroupField['thStyle'] = assignmentWithinGroup.thStyle
              }
            }
            this.assignmentGroupFields.push(assignmentGroupField)
          }

          // create an array 0 up through the top assignment number index
          this.assignmentsArray = [...Array(this.fields.length).keys()]
          this.hasAssignments = true
          this.weightedAverageAssignmentId = data.weighted_score_assignment_id
          this.extraCreditAssignmentId = data.extra_credit_assignment_id
        }
        this.canViewScores = true
      } catch (error) {
        alert(error.message)
      }
    },
    getHeaderColor (key, assignmentGroups) {
      let percent
      for (let j = 0; j < assignmentGroups.length; j++) {
        if (assignmentGroups[j].includes(parseInt(key))) {
          percent = 95 - 7 * j
          return { 'background-color': `hsla(197, 65%, ${percent}%, 1)`, 'align': 'center', 'min-width': '150px' }
        }
      }
    }

  }
}
</script>
<style scoped>
/* an assignment score outside 0 to its points: dark red text on light red, readable in the striped rows */
.score-out-of-range {
  background-color: #fde2e1;
  color: #a71d2a;
  font-weight: 600;
  padding: 1px 4px;
  border-radius: 3px;
}


table thead,
table tfoot {
  position: sticky;
}

table thead {
  inset-block-start: 0; /* "top" */
}

table tfoot {
  inset-block-end: 0; /* "bottom" */
}
</style>
