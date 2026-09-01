<template>
  <div>
    <ImportingCourseModal :importing-course="importingCourse"
                          :importing-course-message="importingCourseMessage"
                          :imported-course="importedCourse"
                          :import-actioning="courseToImportForm.action === 'clone' ? 'Cloning' : 'Importing'"
    />
    <AllFormErrors :all-form-errors="allFormErrors" modal-id="modal-form-errors-course"/>
    <AllFormErrors :all-form-errors="allFormErrors" modal-id="modal-form-errors-delete-course"/>
    <b-modal id="modal-discuss-it-or-clicker-or-open-ended-in-realtime-questions-exist"
             title="Question Settings Within Assignments"
             no-close-on-esc
             no-close-on-backdrop
             size="lg"
    >
      <p>When importing this course, please let us know how you would like us to handle the following.</p>
      <b-form-group
        label-cols-sm="5"
        label-cols-lg="4"
        label-for="reset_discuss_it_settings_to_default"
      >
        <template #label>
          Reset Discuss-it Settings
          <QuestionCircleTooltip :id="'discuss-it-settings-tooltip'"/>
          <b-tooltip target="discuss-it-settings-tooltip"
                     delay="250"
                     triggers="hover focus"
          >
            Regardless of which option you choose, you can adjust the settings at the usage level.
          </b-tooltip>
        </template>
        <b-form-radio-group
          v-show="discussItQuestionsExist"
          id="reset_discuss_it_settings_to_default"
          v-model="resetDiscussItSettingsToDefault"
          class="mt-2"
        >
          <b-form-radio value="1">
            Yes
          </b-form-radio>
          <b-form-radio value="0">
            No
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <b-form-group
        v-show="clickerQuestionsExist"
        label-cols-sm="5"
        label-cols-lg="4"
        label="Reset Clicker Settings"
        label-for="reset_clicker_settings_to_default"
      >
        <template #label>Reset Clicker Settings
          <QuestionCircleTooltip :id="'discuss-it-settings-tooltip'"/>
          <b-tooltip target="discuss-it-settings-tooltip"
                     delay="250"
                     triggers="hover focus"
          >
            Regardless of which option you choose, you can adjust the settings at the usage level.
          </b-tooltip>
        </template>
        <b-form-radio-group
          id="reset_clicker_settings_to_default"
          v-model="resetClickerSettingsToDefault"
          class="mt-2"
        >
          <b-form-radio value="1">
            Yes
          </b-form-radio>
          <b-form-radio value="0">
            No
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <b-form-group
        v-show="openEndedQuestionsInRealTimeAssignmentExist"
        id="remove_open_ended_questions_in_real_time_assignment_in_real_time_assignments"
        label-cols-sm="5"
        label-cols-lg="4"
        label="Open-ended Questions"
      >
        <b-form-radio-group
          v-model="removeOpenEndedQuestionsFromRealTimeAssignments"
          stacked
        >
          <b-form-radio value="1">
            Remove open-ended questions from real time assignments (recommended)
          </b-form-radio>
          <b-form-radio value="0">
            Keep open-ended questions in real time assignments
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="continueToCloneOrImport"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-shift-assignments"
             title="Shift Assignments"
             size="lg"
    >
      <p>
        After cloning this course, you can easily
      </p>
      <b-form-group
        label-cols-sm="3"
        label-cols-lg="2"
        label="Shift Dates"
        label-for="shift_dates"
      >
        <b-form-radio-group
          id="shift_dates"
          v-model="courseToImportForm.shift_dates"
          class="mt-2"
        >
          <b-form-radio value="1">
            Yes
          </b-form-radio>
          <b-form-radio value="0">
            No
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <b-form-group
        v-if="courseToImportForm.shift_dates === '1'"
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="available_from"
        label="New First Available On"
      >
        <b-form-row>
          <b-col lg="7">
            <b-form-datepicker
              id="available_from"
              v-model="courseToImportForm.due_date"
              required
              tabindex="0"
              :min="min"
              class="datepicker"
              :class="{ 'is-invalid': courseToImportForm.errors.has('due_date') }"
            />
            <has-error :form="courseToImportForm" field="due_date"/>
          </b-col>
          <b-col>
            <vue-timepicker v-model="courseToImportForm.due_time"
                            format="h:mm A"
                            manual-input
                            :class="{ 'is-invalid': courseToImportForm.errors.has('due_time') }"
                            input-class="custom-timepicker-class"
                            @input="courseToImportForm.errors.clear('due_time')"
                            @shown="courseToImportForm.errors.clear('due_time')"
            >
              <template v-slot:icon>
                <b-icon-clock/>
              </template>
            </vue-timepicker>
            <ErrorMessage :message="courseToImportForm.errors.get('due_time')"/>
          </b-col>
        </b-form-row>
      </b-form-group>

      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-shift-assignments')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="checkForBeta"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-clone-beta"
      ref="modal"
      title="Clone Beta Course"
    >
      <p>
        This course is a Beta course. You can clone this as another tethered Beta course, using the current state of the
        associated Alpha course
        or you can clone this as an untethered course.
      </p>
      <b-form-group label="Clone the course"
                    label-cols-sm="5"
                    label-cols-lg="4"
                    label-for="clone-the-course-options"
      >
        <b-form-radio-group id="clone-the-course-options"
                            v-model="cloneCourseOption"
                            class="mt-2"
        >
          <b-form-radio name="clone-course-options" value="as-beta">
            as another Beta course
          </b-form-radio>
          <b-form-radio name="clone-course-options" value="untethered">
            an an untethered course
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-clone-beta')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="checkedForDiscussItOrClickerOrOpenEndedInRealTimeQuestions = false;clone(courseToClone)"
        >
          Submit
        </b-button>
      </template>
    </b-modal>

    <b-modal
      id="modal-import-course"
      ref="modal"
      title="Import Course"
      size="lg"
    >
      <div id="course_to_import">
        <v-select
          id="course-to-import"
          v-model="courseToImport"
          class="mb-2"
          :options="formattedImportableCourses"
          label="formatted_course"
          :reduce="option => option.course_id"
          placeholder="Enter a course or instructor name"
          @input="getImportCourseWarnings($event)"
        />
      </div>
      <div v-if="showFormativeMessage">
        <b-alert show variant="info">
          The course you are about to import is a formative course. The questions and solutions are already available to
          students
          without logging in.
        </b-alert>
      </div>
      <b-form-group
        v-if="showImportAsBeta"
        id="beta"
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="beta"
      >
        <template v-slot:label>
          Import as Beta Course
          <span id="beta_course_tooltip">
            <b-icon class="text-muted" icon="question-circle"/></span>
          <b-tooltip target="beta_course_tooltip"
                     delay="250"
          >
            <ImportAsBetaText/>
          </b-tooltip>
        </template>
        <b-form-radio-group v-model="courseToImportForm.import_as_beta" class="mt-2">
          <b-form-radio name="beta" value="1">
            Yes
          </b-form-radio>
          <b-form-radio name="beta" value="0">
            No
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <b-form-group
        label-cols-sm="3"
        label-cols-lg="2"
      >
        <template #label>
          Auto-release
          <a id="explanation_of_auto_release"
             v-b-tooltip="'This course has auto-releases set for at least one assignment (when the assignment is shown, solutions are released, statistics can be viewed for students, and when scores are released). Auto-releases can always be changed at the assignment level after you import the course.'"
             href="#"
             aria-label="Explanation of auto-release"
          >
            <b-icon class="text-muted"
                    icon="question-circle"
            />
          </a>
        </template>
        <b-form-radio-group v-model="courseToImportForm.auto_releases" class="mt-2">
          <b-form-radio name="auto-releases" value="use existing">
            Use existing release dates
          </b-form-radio>
          <b-form-radio name="auto-releases" value="clear existing">
            Clear all existing release dates
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-import-course')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          :disabled="disableYesImportCourse || importingCourse"
          @click="checkedForDiscussItOrClickerOrOpenEndedInRealTimeQuestions=false;handleImportCourse()"
        >
          Yes, import course!
        </b-button>
      </template>
    </b-modal>
    <PageTitle v-if="canViewCourses" title="My Courses"/>
    <b-container v-if="canViewCourses && user && [2,5].includes(user.role)">
      <b-row class="float-right mb-4 d-inline-flex">
        <b-button v-b-modal.modal-course-details variant="primary" class="mr-1"
                  size="sm"
        >
          New Course
        </b-button>
        <b-button v-if="[2,5].includes(user.role)"
                  variant="outline-primary"
                  size="sm"
                  class="mr-1"
                  @click="initImportCourse"
        >
          Import Course
        </b-button>
        <ConsultInsight :url="'https://commons.libretexts.org/insight/course-properties-overview'"/>
      </b-row>
    </b-container>

    <b-modal
      id="modal-course-details"
      ref="modal"
      size="lg"
      :no-close-on-backdrop="true"
      @hidden="resetModalForms"
    >
      <template #modal-header>
        <div class="d-flex align-items-center justify-content-between w-100">
          <h5 class="modal-title mb-0">
            Course Details
          </h5>
          <div class="d-flex align-items-center">
            <ConsultInsight :url="'https://commons.libretexts.org/insight/adapt-course-properties---general-information'"
                            class="mr-2"
            />
            <button type="button"
                    class="close"
                    aria-label="Close"
                    @click="$bvModal.hide('modal-course-details')"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
      </template>
      <CourseForm :form="newCourseForm"/>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-course-details')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="primary"
          class="float-right"
          @click="createCourse"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-cannot-delete-course-with-at-least-one-tethered-beta-course"
      ref="cannotDeleteCourseWithTetheredBetaCourse"
      title="Cannot Delete Course"
      size="sm"
      hide-footer
    >
      <p>
        This is an Alpha course with at least one Beta course so it cannot be deleted. You can always hide this
        course from your students.
      </p>
    </b-modal>
    <b-modal
      id="modal-delete-course"
      ref="modal"
      :title="`Confirm Delete ${courseName}`"
      size="lg"
      @hidden="resetModalForms"
    >
      <b-form ref="form">
        <b-alert show variant="danger">
          <span class="font-weight-bold">Once a course is deleted, it can not be retrieved!</span>
        </b-alert>
        <p>By deleting the course <strong>{{ courseName }}</strong>, you will also delete:</p>
        <ol>
          <li>All assignments associated with the course</li>
          <li>All submitted student responses</li>
          <li>All student scores</li>
        </ol>
        <RequiredText :plural="false"/>
        <b-form-group
          label-cols-sm="1"
          label-cols-lg="2"
          label-for="Confirmation"
        >
          <template v-slot:label>
            Confirmation*
          </template>
          <b-form-input
            id="confirmation"
            v-model="deleteCourseForm.confirmation"
            class="col-8"
            required
            placeholder="Please enter the name of the course."
            type="text"
            :class="{ 'is-invalid': deleteCourseForm.errors.has('confirmation') }"
            @keydown="deleteCourseForm.errors.clear('confirmation')"
          />
          <has-error :form="deleteCourseForm" field="confirmation"/>
        </b-form-group>
      </b-form>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-delete-course')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          :disabled="processingDeletingCourse"
          @click="handleDeleteCourse"
        >
          <span v-if="!processingDeletingCourse">Yes, delete course!</span>
          <span v-if="processingDeletingCourse"><b-spinner small type="grow"/>
            Deleting Course...
          </span>
        </b-button>
      </template>
    </b-modal>

    <div v-if="user && user.role === 4">
      <div v-if="canViewCourses" class="row mb-4 float-right">
        <b-button v-b-modal.modal-course-grader-access-code variant="primary">
          New Course
        </b-button>
      </div>
    </div>
    <b-modal
      id="modal-show-course-warning"
      ref="modal"
      ok-title="I understand"
      title="Verify your course start and end dates"
    >
      <p>
        You are about to unhide this course. Please verify the start and end dates of this course as
        being accurate.
      </p>
      <p class="font-weight-bold">
        Students will not be able to enroll in the course outside of the course dates.
      </p>
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="submitShowCourse();$bvModal.hide('modal-show-course-warning');"
        >
          I understand
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-course-grader-access-code"
      ref="modal"
      ok-title="Submit"
      title="Enroll as Grader"
      @ok="submitAddGraderToCourse"
    >
      <b-form ref="form">
        <p>To become a course grader, please provide the course access code given to you by your instructor.</p>
        <b-form-group
          id="access_code"
          label-cols-sm="4"
          label-cols-lg="3"
          label="Access Code"
          label-for="access_code"
        >
          <b-form-input
            id="access_code"
            v-model="graderForm.access_code"
            type="text"
            :class="{ 'is-invalid': graderForm.errors.has('access_code') }"
            @keydown="graderForm.errors.clear('access_code')"
          />
          <has-error :form="graderForm" field="access_code"/>
        </b-form-group>
      </b-form>
    </b-modal>

    <div v-if="hasCourses">
      <div v-if="user.role === 2 && hasBetaCourses && showBetaCourseDatesWarning">
        <b-alert variant="info" :show="true">
          <p>
            <span class="font-weight-bold">
              You currently have at least one Beta course.  Double check that the course
              dates are accurate as ADAPT uses this information to tether the Alpha assignments to your Beta assignments.
            </span>
          </p>
          <b-button size="sm" variant="info" @click="doNotShowBetaCourseDatesWarnings()">
            Don't Show This Again
          </b-button>
        </b-alert>
      </div>
      <div class="table-responsive">
        <table class="table table-striped" aria-label="Course List">
          <thead>
          <tr>
            <th scope="col">
              Course
            </th>
            <th v-if="[2,4].includes(user.role)" style="width:100px">
                <span v-show="user.role === 2">
                  Shown <a id="course_shown"
                           v-b-tooltip="showCourseShownTooltip"
                           href="#"
                           aria-label="Toggle courses shown"
                ><b-icon class="text-muted"
                         icon="question-circle"
                /></a></span>
              <span v-show="user.role === 4">
                  Sections
                </span>
            </th>
            <th v-if="[2,4].includes(user.role)">
              Term
            </th>
            <th :style="[2,4].includes(user.role) ? 'width:120px' : ''">
              Actions
            </th>
          </tr>
          </thead>
          <tbody is="draggable" v-model="courses" tag="tbody"
                 :options="{disabled : user.role === 4, handle: '.handle'}"
                 @end="saveNewOrder"
          >
          <tr v-for="course in courses"
              :key="course.id"
              :style="!course.shown && user.role === 2 ? 'background: #ffe8e7' : ''"
          >
            <th scope="row">
              <div class="mb-0">
                <b-icon v-if="user.role === 2" icon="list" class="handle"/>
                <span v-show="parseInt(course.alpha) === 1"
                      :id="getTooltipTarget('alphaCourse',course.id)"
                      class="text-muted"
                >&alpha; </span>
                <b-tooltip :target="getTooltipTarget('alphaCourse',course.id)"
                           delay="500"
                >
                  This course is an Alpha course. Adding/removing assignments or assessments from this
                  course will be directly reflected in the associated Beta courses.
                </b-tooltip>
                <span v-if="course.is_co_instructor">
                <b-tooltip :target="getTooltipTarget('coInstructor',course.id)"
                           delay="500"
                >
                 You are a co-instructor in this course. The main instructor is {{ course.main_instructor_name }}.
                </b-tooltip>
                <font-awesome-icon :icon="coInstructorIcon"
                                   :id="getTooltipTarget('coInstructor',course.id)"
                                   class="text-muted"
                />
                  </span>
                <span v-show="parseInt(course.is_beta_course) === 1"
                      :id="getTooltipTarget('betaCourse',course.id)"
                      class="text-muted"
                >&beta; </span>
                <b-tooltip :target="getTooltipTarget('betaCourse',course.id)"
                           delay="500"
                >
                  This course is a Beta course. Since it is tethered to an Alpha course, assignments/assessments which
                  are
                  added/removed in the Alpha course will be directly reflected in this course.
                </b-tooltip>
                <a :href="`/instructors/courses/${course.id}/assignments`">{{ course.name }}</a>
              </div>
            </th>

            <td v-if="[2,4].includes(user.role)">
                <span v-if="user.role === 2">
                  <toggle-button
                    tabindex="0"
                    :width="57"
                    :value="Boolean(course.shown)"
                    :aria-checked="Boolean(course.shown)"
                    :aria-label="Boolean(course.shown) ? `${course.name} shown` : `${course.name} not shown`"
                    :sync="true"
                    :font-size="14"
                    :margin="4"
                    :color="toggleColors"
                    :labels="{checked: 'Yes', unchecked: 'No'}"
                    @change="showCourseWarning(course)"
                  />
                </span>
              <span v-if="user.role === 4">
                  {{ course.sections }}
                </span>
            </td>
            <td v-if="[2,4].includes(user.role)">
              {{ course.term }}
            </td>
            <td>
              <div class="mb-0">
                  <span v-if="[2,4].includes(user.role)" class="pr-1">
                    <b-tooltip :target="getTooltipTarget('gradebook',course.id)"
                               delay="500"
                               triggers="hover focus"
                    >
                      Gradebook
                    </b-tooltip>
                    <a :id="getTooltipTarget('gradebook',course.id)"
                       href=""
                       @click.prevent="showGradebook(course.id)"
                    >
                      <b-icon class="text-muted"
                              icon="file-spreadsheet"
                              :aria-label="`Gradebook for ${course.name}`"
                      />
                    </a>
                  </span>
                <span v-if="user && [2,5].includes(user.role)">
                    <span class="pr-1">
                      <b-tooltip :target="getTooltipTarget('properties',course.id)"
                                 delay="500"
                                 triggers="hover focus"
                      >
                        Course Properties
                      </b-tooltip>
                      <a :id="getTooltipTarget('properties',course.id)"
                         href=""
                         @click.prevent="getProperties(course)"
                      >
                        <b-icon class="text-muted"
                                icon="gear"
                                :aria-label="`Course properties for ${course.name}`"
                        />
                      </a>
                    </span>
                    <span class="pr-1">
                      <a :id="getTooltipTarget('clone',course.id)"
                         href=""
                         @click.prevent="initCloneCourse(course)"
                      >
                        <font-awesome-icon
                          class="text-muted"
                          :icon="copyIcon"
                          :aria-label="`Clone ${course.name}`"
                        />
                      </a>
                      <b-tooltip :target="getTooltipTarget('clone',course.id)"
                                 delay="500"
                      >
                        Clone {{ course.name }}
                      </b-tooltip>
                    </span>

                    <b-tooltip :target="getTooltipTarget('deleteCourse',course.id)"
                               delay="500"
                               triggers="hover focus"
                    >
                      Delete Course
                    </b-tooltip>
                    <a :id="getTooltipTarget('deleteCourse',course.id)"
                       href=""
                       @click.prevent="deleteCourse(course)"
                    >
                      <b-icon class="text-muted"
                              icon="trash"
                              :aria-label="`Delete ${course.name}`"
                      />
                    </a>

                  </span>
              </div>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div v-else>
      <br>
      <div class="mt-4">
        <b-alert :show="showNoCoursesAlert" variant="warning">
          <a href="#" class="alert-link">You currently have no
            courses.
          </a>
        </b-alert>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { mapGetters } from 'vuex'
import { getTooltipTarget, initTooltips } from '~/helpers/Tooptips'
import CourseForm from '~/components/CourseForm'
import Form from 'vform'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import { ToggleButton } from 'vue-js-toggle-button'
import ImportAsBetaText from '~/components/ImportAsBetaText'
import AllFormErrors from '~/components/AllFormErrors'
import draggable from 'vuedraggable'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { faUsers } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { isMobile } from '~/helpers/mobileCheck'
import ImportingCourseModal from '~/components/ImportingCourseModal.vue'
import VueTimepicker from 'vue2-timepicker'
import 'vue2-timepicker/dist/VueTimepicker.css'
import ErrorMessage from '~/components/ErrorMessage.vue'
import { initCentrifuge } from '~/helpers/Centrifuge'
import ConsultInsight from '../../components/ConsultInsight.vue'

export default {
  components: {
    ConsultInsight,
    ErrorMessage,
    VueTimepicker,
    ImportingCourseModal,
    CourseForm,
    ToggleButton,
    FontAwesomeIcon,
    ImportAsBetaText,
    AllFormErrors,
    draggable
  },
  middleware: 'auth',
  data: () => ({
    openEndedQuestionsInRealTimeAssignmentExist: false,
    coInstructorIcon: faUsers,
    discussItQuestionsExist: false,
    clickerQuestionsExist: false,
    importType: '',
    resetDiscussItSettingsToDefault: '1',
    resetClickerSettingsToDefault: '1',
    removeOpenEndedQuestionsFromRealTimeAssignments: '1',
    checkedForDiscussItOrClickerOrOpenEndedInRealTimeQuestions: false,
    centrifuge: {},
    importedCourse: { name: '', id: 0 },
    importingCourseMessage: {},
    showFormativeMessage: false,
    timeZones: [],
    form: new Form({
      time_zone: ''
    }),
    cloneCourseOption: null,
    courseToClone: {},
    copyIcon: faCopy,
    processingDeletingCourse: false,
    deleteCourseForm: new Form({
      confirmation: ''
    }),
    courseName: '',
    importingCourse: false,
    toggleColors: window.config.toggleColors,
    currentOrderedCourses: [],
    allFormErrors: [],
    showBetaCourseDatesWarning: true,
    hasBetaCourses: false,
    disableYesImportCourse: true,
    importAsBeta: 0,
    showImportAsBeta: false,
    formattedImportableCourses: [],
    importableCourses: [],
    courseToImport: null,
    showCourseShownTooltip: {
      fallbackPlacement: ['right'],
      placement: 'right',
      title: 'Show or hide a course on the student\'s homepage.  If you are embedding assignments, please show/hide individual assignments; hiding the course won\'t hide the individually embedded assignments.'
    },
    fields: [],
    courses: [],
    course: null,
    hasCourses: false,
    courseId: false, // if there's a courseId if it's an update
    showNoCoursesAlert: false,
    canViewCourses: false,
    modalHidden: false,
    min: '',
    graderForm: new Form({
      access_code: ''
    }),
    courseToImportForm: new Form({
      import_as_beta: 0,
      shift_dates: '',
      due_date: '',
      due_time: '',
      auto_releases: 'use existing'
    }),
    newCourseForm: new Form({
      school: '',
      name: '',
      beta: '0',
      alpha: '0',
      term: 'N/A',
      lms: '0',
      grade_passback: 'automatic',
      public_description: '',
      private_description: '',
      section: 'Main',
      crn: '1',
      start_date: '',
      end_date: '',
      public: '0',
      whitelisted_domains: [],
      anonymous_users: '0',
      formative: '0'
    })
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  watch: {
    courseToImport (newValue, oldValue) {
      if (newValue !== oldValue) {
        this.courseToImportForm.import_as_beta = 0
        this.courseToImportForm.auto_releases = false
        this.showImportAsBeta = false
        this.disableYesImportCourse = true
      }
    }
  },
  beforeDestroy () {
    window.removeEventListener('keydown', this.quickSave)
    window.removeEventListener('keydown', this.forceImportModalClose)
    try {
      if (this.centrifuge) {
        this.centrifuge.disconnect()
      }
    } catch (error) {
      // won't be a function for all the other ones that haven't been defined on the page
    }
  },
  mounted () {
    if (this.user.role === 3) {
      this.$router.push({ name: 'students.courses.index' })
      return
    }
    window.addEventListener('keydown', this.quickSave)
    window.addEventListener('keydown', this.forceImportModalClose)
    this.getCourses()
    this.getLastCourseSchool()

    let atIndex = this.user.email.indexOf('@')
    let domain = this.user.email.slice(atIndex + 1)
    this.newCourseForm.whitelisted_domains = [domain]
    this.getTooltipTarget = getTooltipTarget
    initTooltips(this)
    this.fields = (this.user.role === 2)
      ? [{
        key: 'name',
        label: 'Course',
        sortable: true
      },
        'shown',
        {
          key: 'start_date',
          sortable: true
        },
        {
          key: 'end_date',
          sortable: true
        },
        'actions'
      ]
      : [{
        key: 'name',
        label: 'Course',
        sortable: true
      },
        'sections',
        {
          key: 'start_date',
          sortable: true
        },
        {
          key: 'end_date',
          sortable: true
        },
        'actions'
      ]
  },
  methods: {
    initCentrifuge,
    isMobile,
    async continueToCloneOrImport () {
      this.checkedForDiscussItOrClickerOrOpenEndedInRealTimeQuestions = true
      this.$bvModal.hide('modal-discuss-it-or-clicker-or-open-ended-in-realtime-questions-exist')
      this.importType === 'clone'
        ? await this.clone(this.courseToClone)
        : await this.handleImportCourse()
    },
    forceImportModalClose (event) {
      if (event.key === 'Escape') {
        this.$bvModal.hide('modal-import-course')
      }
    },
    quickSave (event) {
      if (event.ctrlKey && event.key === 'S') {
        this.createCourse()
      }
    },
    initCloneCourse (course) {
      // no longer do the shift assignment modal
      this.importType = 'clone'
      this.courseToClone = course
      this.cloneCourseOption = null
      this.checkForBeta()
    },
    checkForBeta () {
      this.checkedForDiscussItOrClickerOrOpenEndedInRealTimeQuestions = false
      if (this.courseToClone.is_beta_course) {
        this.cloneCourseOption = 'as-beta'
        this.$bvModal.show('modal-clone-beta')
      } else {
        this.clone(this.courseToClone)
      }
    },
    async getAlphaCourseFromBetaCourse (course) {
      try {
        const { data } = await axios.get(`/api/beta-courses/get-alpha-course-from-beta-course/${course.id}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        return data.alpha_course
      } catch (error) {
        this.$noty.error(error.message)
      }
      return false
    },
    async checkForDiscussItClickerOrOpenEndedQuestionsInRealTimeAssignment (course, importType) {
      try {
        const { data } = await axios.get(`/api/assignment-sync-question/discuss-it-clicker-or-open-ended-questions-by-course-or-assignment/course/${course.id}`)
        if (data.type === 'success') {
          this.resetClickerSettingsToDefault = '0'
          this.resetDiscussItSettingsToDefault = '0'
          this.removeOpenEndedQuestionsFromRealTimeAssignments = '0'
          this.discussItQuestionsExist = data.discuss_it_questions_exist
          if (this.discussItQuestionsExist) {
            this.resetDiscussItSettingsToDefault = '1'
          }
          this.clickerQuestionsExist = data.clicker_questions_exist
          if (this.clickerQuestionsExist) {
            this.resetClickerSettingsToDefault = '1'
          }
          this.openEndedQuestionsInRealTimeAssignmentExist = data.open_ended_questions_in_real_time_assignment_exist
          if (this.openEndedQuestionsInRealTimeAssignmentExist){
            this.removeOpenEndedQuestionsFromRealTimeAssignments = '1'
          }
          if (this.discussItQuestionsExist || this.clickerQuestionsExist || this.openEndedQuestionsInRealTimeAssignmentExist) {
            this.$bvModal.show('modal-discuss-it-or-clicker-or-open-ended-in-realtime-questions-exist')
          } else {
            this.checkedForDiscussItOrClickerOrOpenEndedInRealTimeQuestions = true
            this.importType = importType
            this.importType === 'clone' ? await this.clone(course) : await this.handleImportCourse()
          }
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async clone (course) {
      if (!this.checkedForDiscussItOrClickerOrOpenEndedInRealTimeQuestions) {
        this.courseToClone = course
        this.resetDiscussItSettingsToDefault = '1'
        await this.checkForDiscussItClickerOrOpenEndedQuestionsInRealTimeAssignment(course, 'clone')
        return
      }
      this.courseToImportForm.action = 'clone'
      if (this.cloneCourseOption === 'as-beta') {
        course = await this.getAlphaCourseFromBetaCourse(course)
        this.$bvModal.hide('modal-clone-beta')
        if (!course) {
          return false
        }
        this.courseToImportForm.import_as_beta = true
      }
      this.centrifuge = await this.initCentrifuge()
      const sub = this.centrifuge.newSubscription(`import-copy-course-${this.user.id}`)
      const courseImportedCopied = this.courseImportedCopied
      sub.on('publication', function (ctx) {
        console.log(ctx)
        courseImportedCopied(ctx)
      }).subscribe()
      this.importingCourse = true
      this.importedCourse = {
        name: course.name,
        id: course.id
      }
      this.courseToImportForm.reset_discuss_it_settings_to_default = +this.resetDiscussItSettingsToDefault === 1
      this.courseToImportForm.reset_clicker_settings_to_default = +this.resetClickerSettingsToDefault === 1
      this.courseToImportForm.remove_open_ended_questions_from_real_time_assignments = +this.removeOpenEndedQuestionsFromRealTimeAssignments === 1

      try {
        const { data } = await this.courseToImportForm.post(`/api/courses/import/${course.id}`)
        this.$bvModal.hide('modal-clone-beta')
        if (data.type === 'error') {
          this.importingCourse = false
          this.$noty.error(data.message)
          return false
        }
        this.$bvModal.hide('modal-shift-assignments')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
        this.importingCourse = false
      }
      if (this.importingCourse) {
        this.$bvModal.hide('modal-clone-beta')
        this.$bvModal.show(`modal-importing-course-${course.id}`)
      }
    },
    async saveNewOrder () {
      let orderedCourses = []
      for (let i = 0; i < this.courses.length; i++) {
        orderedCourses.push(this.courses[i].id)
      }

      let noChange = true
      for (let i = 0; i < this.currentOrderedCourses.length; i++) {
        if (this.currentOrderedCourses[i] !== this.courses[i]) {
          noChange = false
        }
      }
      if (noChange) {
        return false
      }
      try {
        const { data } = await axios.patch(`/api/course-orders`, { ordered_courses: orderedCourses })
        this.$noty[data.type](data.message)

        if (data.type === 'success') {
          for (let i = 0; i < this.courses.length; i++) {
            this.courses[i].order = i + 1
          }
          this.currentOrderedCourses = this.courses
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async doNotShowBetaCourseDatesWarnings () {
      try {
        const { data } = await axios.post(`/api/beta-courses/do-not-show-beta-course-dates-warning`)
        if (data.type === 'error') {
          this.$noty.error(data.message)

          return false
        }
        this.showBetaCourseDatesWarning = false
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getImportCourseWarnings (courseId) {
      this.importAsBeta = 0
      this.showFormativeMessage = false
      if (!courseId) {
        return false
      }
      try {
        const { data } = await axios.get(`/api/courses/warnings/${courseId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.showFormativeMessage = Boolean(data.formative)
        if (data.alpha === 1 && this.user.email !== 'commons@libertexts.org') {
          this.showImportAsBeta = true
        }
        if (data.has_auto_releases) {
          this.courseToImportForm.auto_releases = 'use existing'
        }
        this.disableYesImportCourse = false
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getLastCourseSchool () {
      try {
        const { data } = await axios.get(`/api/courses/last-school`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)

          return false
        }
        this.newCourseForm.school = data.last_school_name
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async courseImportedCopied (ctx) {
      console.log(ctx)
      this.importingCourseMessage = ctx.data
      this.importingCourse = false
      await this.getCourses()
    },
    async initImportCourse () {
      this.importType = 'import'
      this.disableYesImportCourse = true
      this.importAsBeta = 0
      this.showImportAsBeta = false
      try {
        const { data } = await axios.get(`/api/courses/importable`)
        if (data.type === 'error') {
          this.$noty.error(data.message)

          return false
        }
        this.importableCourses = data.importable_courses
        this.formattedImportableCourses = data.importable_courses.map(course => ({
          course_id: course.course_id,
          formatted_course: course.formatted_course
        }))
        this.courseToImport = null
        this.$bvModal.show('modal-import-course')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getFormattedCourseName (courseId) {
      const found = this.importableCourses.find(course => course.course_id === courseId)
      return found ? found.formatted_course : ''
    },
    async handleImportCourse () {
      if (!this.checkedForDiscussItOrClickerOrOpenEndedInRealTimeQuestions) {
        this.resetDiscussItSettingsToDefault = '0'
        this.resetClickerSettingsToDefault = '0'
        this.removeOpenEndedQuestionsFromRealTimeAssignments = '0'
        await this.checkForDiscussItClickerOrOpenEndedQuestionsInRealTimeAssignment({ id: this.courseToImport }, 'import')
        return
      }
      this.centrifuge = await initCentrifuge()
      const sub = this.centrifuge.newSubscription(`import-copy-course-${this.user.id}`)
      const courseImportedCopied = this.courseImportedCopied
      sub.on('publication', function (ctx) {
        courseImportedCopied(ctx)
      }).subscribe()
      this.importingCourse = true
      try {
        const IdOfCourseToImport = this.courseToImport
        this.courseToImportForm.action = 'import'
        this.importedCourse = { name: this.getFormattedCourseName(IdOfCourseToImport), id: IdOfCourseToImport }
        this.courseToImportForm.reset_discuss_it_settings_to_default = +this.resetDiscussItSettingsToDefault === 1
        this.courseToImportForm.reset_clicker_settings_to_default = +this.resetClickerSettingsToDefault === 1
        this.courseToImportForm.remove_open_ended_questions_from_real_time_assignments = +this.removeOpenEndedQuestionsFromRealTimeAssignments === 1

        const { data } = await this.courseToImportForm.post(`/api/courses/import/${IdOfCourseToImport}`)
        this.courseToImport = null
        if (data.type === 'error') {
          this.$noty.error(data.message)
          this.importingCourse = false
          return false
        }
        this.$bvModal.hide('modal-import-course')
        this.$bvModal.show(`modal-importing-course-${IdOfCourseToImport}`)
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
          this.importingCourse = false
        }
      }
      this.courseToImport = null
    },
    showCourseWarning (course) {
      this.course = course
      this.course.shown || this.user.email === 'commons@libretexts.org'
        ? this.submitShowCourse()
        : this.$bvModal.show('modal-show-course-warning')
    },
    async submitShowCourse () {
      try {
        const { data } = await axios.patch(`/api/courses/${this.course.id}/show-course/${Number(this.course.shown)}`)
        this.$noty[data.type](data.message)

        if (data.type === 'error') {
          return false
        }
        this.course.shown = !this.course.shown
        this.course.access_code = data.course_access_code
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitAddGraderToCourse (bvModalEvt) {
      bvModalEvt.preventDefault()
      try {
        const { data } = await this.graderForm.post('/api/graders')
        this.$noty[data.type](data.message)

        if (data.type === 'success') {
          this.resetAll('modal-course-grader-access-code')
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    async createCourse () {
      try {
        const { data } = await this.newCourseForm.post('/api/courses')
        this.$noty[data.type](data.message)

        this.resetAll('modal-course-details')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.newCourseForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-course')
        }
      }
    },
    getProperties (course) {
      this.$router.push(`/instructors/courses/${course.id}/properties`)
    },
    showGradebook (courseId) {
      this.$router.push(`/courses/${courseId}/gradebook`)
    },
    async deleteCourse (course) {
      this.courseId = course.id
      this.courseName = course.name
      try {
        const { data } = await axios.get(`/api/beta-courses/get-from-alpha-course/${this.courseId}`)
        if (data.type !== 'success') {
          return false
        }
        data.beta_courses.length
          ? this.$bvModal.show('modal-cannot-delete-course-with-at-least-one-tethered-beta-course')
          : this.$bvModal.show('modal-delete-course')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async handleDeleteCourse () {
      this.processingDeletingCourse = true
      try {
        const { data } = await this.deleteCourseForm.delete(`/api/courses/${this.courseId}`)
        this.$noty[data.type](data.message)
        this.resetAll('modal-delete-course')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.deleteCourseForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-delete-course')
        }
      }
      this.processingDeletingCourse = false
    },
    editCourse (course) {
      this.$refs.tooltip.$emit('close')
      this.courseId = course.id
      this.form.name = course.name
      this.form.start_date = course.start_date
      this.form.end_date = course.end_date
      this.$bvModal.show('modal-course-details')
    },
    resetModalForms () {
      this.newCourseForm.name = ''
      this.newCourseForm.start_date = ''
      this.newCourseForm.end_date = ''
      this.courseId = false
      this.newCourseForm.errors.clear()
    },
    resetAll (modalId) {
      this.courseId = ''
      this.courseName = ''
      this.deleteCourseForm.confirmation = ''
      this.getCourses()
      this.resetModalForms()
      // Hide the modal manually
      this.$nextTick(() => {
        this.$bvModal.hide(modalId)
      })
    },
    async getCourses () {
      try {
        const { data } = await axios.get('/api/courses')
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.canViewCourses = true
          this.hasCourses = data.courses && data.courses.length > 0
          this.showNoCoursesAlert = !this.hasCourses
          this.showBetaCourseDatesWarning = data.showBetaCourseDatesWarning
          this.courses = data.courses
          this.hasBetaCourses = this.courses.filter(course => course.is_beta_course).length
          this.currentOrderedCourses = this.courses
          console.log(data.courses)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  },
  metaInfo () {
    return { title: 'My Courses' }
  }
}
</script>
<style>
body, html {
  overflow: visible;

}

svg:focus, svg:active:focus {
  outline: none !important;
}
</style>
