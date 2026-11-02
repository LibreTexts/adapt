<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-assignment-form'" />
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-link-course-to-lms-form'" />
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-canvas-url-form'" />

    <PageTitle v-if="canViewAssignments"
               :consult-insight-url="user && user.role === 2 ? 'https://commons.libretexts.org/insight/creating-a-new-adapt-assignment' :''"
               :title="title"
    >
      <template #actions>
        <!--
          The page itself says these are assignments, so the buttons stay short. They come in two groups:
          Add / Import act on assignments, Gradebook / Properties are for the whole course.
        -->
        <span v-if="user && [2,5].includes(user.role)" class="header-button-group">
          <b-button v-b-tooltip.hover.bottom="'Add a new assignment to this course'"
                    size="sm"
                    variant="primary"
                    aria-label="Add assignment"
                    @click="addAssignmentIsImport=false;confirmInitAddAssignment()"
          >
            Add
          </b-button>
          <b-button v-if="user.role === 2"
                    v-b-tooltip.hover.bottom="'Import an assignment from another course'"
                    class="ml-1"
                    size="sm"
                    variant="primary"
                    aria-label="Import assignment"
                    @click="addAssignmentIsImport=true;confirmInitImportAssignment()"
          >
            Import
          </b-button>
        </span>
        <span v-if="(!course.formative && [2,4].includes(user.role)) || user.role === 2" class="header-button-group">
          <b-button v-if="!course.formative && [2,4].includes(user.role)"
                    v-b-tooltip.hover.bottom="'Course gradebook: scores for every assignment in this course'"
                    size="sm"
                    variant="outline-primary"
                    aria-label="Course gradebook"
                    @click="getGradeBook()"
          >
            Gradebook
          </b-button>
          <b-button v-if="user && user.role === 2"
                    v-b-tooltip.hover.bottom="'Course properties: settings for the whole course'"
                    :class="{ 'ml-1': !course.formative }"
                    size="sm"
                    variant="outline-primary"
                    aria-label="Course properties"
                    @click="$router.push(`/instructors/courses/${courseId}/properties`)"
          >
            Properties
          </b-button>
        </span>
      </template>
    </PageTitle>
    <b-modal id="modal-confirm-link-course"
             title="Confirmation"
    >
      <p>Please confirm that you would like to link the following Canvas course:</p>
      <p class="text-center font-weight-bold">
        {{ linkCourseToLMSForm.lms_course_name }}
      </p>
      <template #modal-footer="{ cancel, ok }">
        <b-button size="sm"
                  @click="$bvModal.hide('modal-confirm-link-course')"
        >
          Cancel
        </b-button>
        <b-button size="sm"
                  variant="primary"
                  @click="submitManualCourseLink"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-copy-course-url"
             title="Copy Course URL"
             size="lg"
    >
      <ul>
        <li>Navigate to your Canvas course</li>
        <li>Copy the URL that you see in your browser</li>
      </ul>
      <b-embed
        type="iframe"
        aspect="16by9"
        src="https://www.youtube.com/embed/Cxm-y9Ku4LM?rel=0"
        allowfullscreen
      />
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  @click="$bvModal.hide('modal-copy-course-url')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-link-course-manually"
             title="Link Course Manually"
             size="lg"
    >
      <p>
        Canvas allows at most 100 courses to be returned. Because of this, some of your courses may not appear on the
        list.
        You can manually link the course by providing the
        course URL. Navigate to the course and copy the
        URL that <a href="" @click.prevent="$bvModal.show('modal-copy-course-url')">you see in your browser</a>.
      </p>
      <b-form-group
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="canvas-course-url"
        label="Canvas Course URL*"
      >
        <b-form-input
          id="crn"
          v-model="canvasCourseUrlForm.url"
          type="text"
          placeholder=""
          required
          :class="{ 'is-invalid': errorMessage }"
          @keydown="errorMessage = ''"
        />
        <ErrorMessage :message="errorMessage" />
      </b-form-group>
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  @click="validateLinkCourseByCanvasUrl"
        >
          Link Course
        </b-button>
      </template>
    </b-modal>
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <b-modal id="modal-non-matching-releases"
               title="Auto-Release Settings Do Not Match"
               size="lg"
      >
        <p>The auto-release settings for the imported assignment do not match your default auto-release settings:</p>

        <table class="table table-striped table-sm">
          <thead>
          <tr>
            <th scope="col">
              Show
            </th>
            <th scope="col">
              Timing
            </th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="(nonMatchingAutoRelease, nonMatchingAutoReleaseIndex) in nonMatchingAutoReleases"
              :key="`auto-releases-${nonMatchingAutoReleaseIndex}`"
          >
            <td>
              {{ nonMatchingAutoRelease.label }}
            </td>
            <td>
              <b-form-group>
                <b-form-radio v-model="importedAssignmentAutoRelease[nonMatchingAutoRelease.key]"
                              :name="`imported-assignment-auto-release-${nonMatchingAutoRelease.key}`"
                              :value="nonMatchingAutoRelease.course_default"
                >
                  {{ nonMatchingAutoRelease.course_default ? nonMatchingAutoRelease.course_default : 'N/A' }} (Course
                  Default)
                </b-form-radio>
                <b-form-radio v-model="importedAssignmentAutoRelease[nonMatchingAutoRelease.key]"
                              :name="`imported-assignment-auto-release-${nonMatchingAutoRelease.key}`"
                              :value="nonMatchingAutoRelease.assignment"
                >
                  {{ nonMatchingAutoRelease.assignment ? nonMatchingAutoRelease.assignment : 'N/A' }}(Imported
                  Assignment)
                </b-form-radio>
              </b-form-group>
            </td>
          </tr>
          </tbody>
        </table>
        <template #modal-footer="{ cancel, ok }">
          <b-button size="sm" @click="$bvModal.hide('modal-non-matching-releases')">
            Cancel
          </b-button>
          <b-button size="sm"
                    variant="primary"
                    @click="handleImportAssignment"
          >
            Continue with Import
          </b-button>
        </template>
      </b-modal>
      <b-modal id="modal-resync-assignment"
               title="Re-sync Assignment"
      >
        <p>
          You are about to re-sync <strong>{{ assignmentToResync.name }}</strong>. ADAPT will look for the Canvas
          assignment
          <strong>{{ assignmentToResync.name }} (ADAPT)</strong> and if it exists, the Canvas assignment will be
          deleted,
          along with any student
          scores and a new assignment will be created. Otherwise, a new Canvas assignment will be created.
        </p>
        <p>Are you sure that you would like to re-sync your ADAPT assignment to Canvas?</p>
        <template #modal-footer="{ cancel, ok }">
          <b-button size="sm" @click="$bvModal.hide('modal-resync-assignment')">
            Cancel
          </b-button>
          <b-button size="sm"
                    variant="primary"
                    @click="reSyncAssignment"
          >
            Re-sync Assignment
          </b-button>
        </template>
      </b-modal>
      <b-modal id="modal-resync-results"
               title="Re-sync Results"
      >
        <b-table
          aria-label="Re-sync results"
          striped
          hover
          :no-border-collapse="true"
          :fields="fields"
          :items="resyncResults"
        >
          <template v-slot:cell(canvas_assignment)="data">
            <span :class="data.itemresynced ? 'text-success' : ''">{{ data.item.canvas_assignment }}</span>
          </template>
          <template v-slot:cell(adapt_assignment)="data">
            <span :class="data.item.resynced ? 'text-success' : ''">{{ data.item.adapt_assignment }}</span>
          </template>
        </b-table>
        <template #modal-footer="{ ok }">
          <b-button size="sm" variant="primary" @click="$bvModal.hide('modal-resync-results')">
            OK
          </b-button>
        </template>
      </b-modal>
      <b-modal id="modal-unlink-lms-assignment"
               :title="`Unlink ${assignmentToUnlink.name}`"
               size="lg"
      >
        <p>
          Please confirm that you would like to unlink the ADAPT assignment
          <strong>{{ assignmentToUnlink.name }}</strong> <span v-show="assignmentToUnlink.lms_assignment_name">
            from your LMS assignment <strong>{{
            assignmentToUnlink.lms_assignment_name
          }}</strong> in your LMS course <strong>{{
            assignmentToUnlink.lms_course_name
          }}</strong>
          </span>.
        </p>
        <p>Your students will not be able to access this ADAPT assignment until it is relinked in your LMS.</p>
        <template #modal-footer="{ cancel, ok }">
          <b-button size="sm" @click="$bvModal.hide('modal-unlink-lms-assignment')">
            Cancel
          </b-button>
          <b-button size="sm"
                    variant="danger"
                    @click="handleUnlinkAssignment"
          >
            Unlink Assignment
          </b-button>
        </template>
      </b-modal>

      <b-modal id="modal-assignment-status"
               title="Explanation of Assignment Status"
               size="lg"
      >
        <table v-if="assignments.length" :key="`link-to-lms-${updateKey}`" class="table table-striped">
          <thead>
          <tr>
            <th scope="col">
              Assignment Status
            </th>
            <th scope="col">
              Explanation
            </th>
          </tr>
          </thead>
          <tbody>
          <tr>
            <th>Upcoming</th>
            <td>
              Students cannot yet access the assignment.
            </td>
          </tr>

          <tr>
            <th>Open</th>
            <td>
              Students can access the assignment and submit.
            </td>
          </tr>
          <tr>
            <th>Partial</th>
            <td>
              Based on differing due dates, only some students can access the assignment and submit.
            </td>
          </tr>
          <tr>
            <th>Closed</th>
            <td>
              Students can access the assignment yet no longer submit since the due date has passed.
            </td>
          </tr>
          <tr>
            <th>
              Released
            </th>
            <td>
              Students can access the assignment but they can't submit because the assignment is a delayed assignment
              and either the solutions are shown or the scores are released.
              Click the arrow to the left of the assignment to show/hide the solutions or scores.
            </td>
          </tr>
          </tbody>
        </table>
        <template #modal-footer="{ cancel, ok }">
          <b-button size="sm" variant="primary" @click="$bvModal.hide('modal-assignment-status')">
            OK
          </b-button>
        </template>
      </b-modal>
      <b-modal
        id="modal-link-assignments-to-lms"
        title="Link Course to LMS"
      >
        <b-alert show variant="success">
          The course has been successfully linked. <span v-if="assignments.length">Assignments are now being linked.</span>
        </b-alert>
        <table v-if="assignments.length" :key="`link-to-lms-${updateKey}`" class="table table-striped">
          <thead>
          <tr>
            <th scope="col">
              Assignment
            </th>
            <th scope="col">
              Submission Status
            </th>
          </tr>
          </thead>
          <tr v-for="(assignment,index) in assignments" :key="`assignments-${index}`">
            <td>{{ assignment.name }}</td>
            <td v-if="assignment.link_to_lms">
              <span :class="assignment.link_to_lms.message_class">{{ assignment.link_to_lms.message }}</span>
            </td>
          </tr>
        </table>
        <template #modal-footer>
          <b-button size="sm"
                    variant="primary"
                    @click="$bvModal.hide('modal-link-assignments-to-lms')"
          >
            OK
          </b-button>
        </template>
      </b-modal>
      <b-modal
        id="modal-confirm-unlink-lms-course"
        title="Unlink course from LMS"
      >
        <p>
          Please confirm whether you would like to unlink this course from your LMS course <span
          class="font-weight-bold"
        >{{ course.lms_course }}</span>.
          If you unlink this course, your students will no longer be able to access the course through your LMS until
          you
          relink the course.
        </p>
        <p>Though the course will become unlinked, it will not be deleted from your LMS nor from ADAPT.</p>
        <template #modal-footer="{ cancel, ok }">
          <b-button size="sm" @click="$bvModal.hide('modal-confirm-unlink-lms-course')">
            Cancel
          </b-button>
          <b-button size="sm"
                    variant="primary"
                    @click="unlinkCourseFromLMS"
          >
            Unlink Course
          </b-button>
        </template>
      </b-modal>
      <b-modal
        id="modal-confirm-add-untethered-assignment"
        ref="modal"
        title="Confirm New Untethered Assignment"
      >
        <p>
          You are about to {{ addAssignmentIsImport ? 'import' : 'add' }} an untethered assignment to a tethered course.
          If you are presenting the course within
          the context
          of a Libretext book, you will have to manually create a page with this assignment so that your students can
          access it.
        </p>
        <p>
          If, on the other hand, you are presenting your assignments within the ADAPT platform, the only consequence of
          adding an
          untethered assignment is that these will not be auto-updated via an Alpha course.
        </p>
        <template #modal-footer="{ cancel, ok }">
          <b-button size="sm" @click="$bvModal.hide('modal-confirm-add-untethered-assignment')">
            Cancel
          </b-button>
          <b-button size="sm" variant="primary"
                    @click="$bvModal.hide('modal-confirm-add-untethered-assignment');addUntetheredAssignment()"
          >
            {{ addAssignmentIsImport ? 'Import' : 'Add' }} Untethered Assignment
          </b-button>
        </template>
      </b-modal>
      <b-modal
        id="modal-cannot-delete-beta-assignment"
        ref="modal"
        title="Cannot Delete"
        size="sm"
        hide-footer
      >
        This assignment is a Beta assignment. Since it is tethered to a corresponding assigment in an
        Alpha course, it cannot be deleted.
      </b-modal>
      <b-modal
        id="modal-assignment-properties"
        ref="modal"
        title="Assignment Properties"
        ok-title="Submit"
        size="lg"
        no-close-on-backdrop
        @hidden="resetAssignmentForm(form,assignmentId)"
        @shown="updateModalToggleIndex('modal-assignment-properties')"
      >
        <AssignmentProperties
          :key="`assignment-properties-${assignmentId}`"
          :assignment-groups="assignmentGroups"
          :form="form"
          :course-id="parseInt(courseId)"
          :course-start-date="course.start_date"
          :course-end-date="course.end_date"
          :assignment-id="assignmentId"
          :is-beta-assignment="isBetaAssignment"
          :lms="!!lms"
          :lms-api="form.lms_api"
          :has-submissions-or-file-submissions="hasSubmissionsOrFileSubmissions"
          :is-alpha-course="Boolean(course.alpha)"
          :is-formative-course="Boolean(course.formative)"
          :is-formative-assignment="Boolean(isFormativeAssignment) || form.isFormativeAssignment"
          :overall-status-is-not-open="overallStatusIsNotOpen"
          :owns-all-questions="Boolean(ownsAllQuestions)"
          :anonymous-users="Boolean(course.anonymous_users)"
          :course="course"
          @populateFormWithAssignmentTemplate="populateFormWithAssignmentTemplate"
        />
        <template #modal-footer="{ cancel, ok }">
          <b-button size="sm" @click="$bvModal.hide('modal-assignment-properties')">
            Cancel
          </b-button>
          <b-button v-show="!savingAssignment"
                    size="sm"
                    variant="primary"
                    @click="handleSubmitAssignmentInfo()"
          >
            Save
          </b-button>
          <span v-show="savingAssignment" class="pl-2">
            <b-spinner small type="grow" />
            Saving...processing
          </span>
        </template>
      </b-modal>
      <b-modal
        id="modal-assign-tos-to-view"
        ref="modal"
        title="Assigned To"
        size="lg"
      >
        <AssignTosToView ref="assignTosModal" :assign-tos-to-view="assignTosToView" />
      </b-modal>

      <b-modal
        id="modal-create-assignment-from-template"
        ref="modal"
        size="lg"
        :title="`Clone ${createAssignmentFromTemplateAssignmentName}`"
      >
        <b-form-group
          id="create_assignment_from_template_level"
          label-cols-sm="3"
          label-cols-lg="3"
          label-class="pt-0"
          label="Level"
          label-for="Level"
        >
          <b-form-radio-group
            v-model="createAssignmentFromTemplateForm.level"
            stacked
          >
            <b-form-radio value="properties_and_questions">
              Properties and questions
            </b-form-radio>
            <b-form-radio value="properties_and_not_questions">
              Just the properties
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>
        <b-alert
          v-if="createAssignmentFromTemplateForm.level === 'properties_and_questions'"
          show
          variant="info"
          class="mt-2"
        >
          <small>
            <strong>Note:</strong> Questions in the clone will be linked to the source assignment's questions; they will
            not be independent copies.
          </small>
        </b-alert>
        <b-form-group
          id="create_assignment_from_template_assign_to_groups"
          label-cols-sm="3"
          label-cols-lg="3"
          label-class="pt-0"
          label="Assign To's"
          label-for="Assign To's"
        >
          <b-form-radio-group
            v-model="createAssignmentFromTemplateForm.assign_to_groups"
            stacked
          >
            <b-form-radio value="1">
              Copy groups and associated times
            </b-form-radio>
            <b-form-radio value="0">
              Don't copy groups and associated times
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>

        <b-form-group
          v-if="discussItQuestionsExist && createAssignmentFromTemplateForm.level === 'properties_and_questions'"
          id="reset_discuss_it_settings_to_default"
          label-cols-sm="3"
          label-cols-lg="3"
          label-class="pt-0"
          label="Discuss-it Settings"
        >
          <b-form-radio-group
            v-model="createAssignmentFromTemplateForm.reset_discuss_it_settings_to_default"
            stacked
          >
            <b-form-radio value="0">
              Copy current settings
            </b-form-radio>
            <b-form-radio value="1">
              Reset settings to default settings
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>
        <b-form-group
          v-if="discussItQuestionsExist && createAssignmentFromTemplateForm.level === 'properties_and_questions'"
          id="link_discuss_it_questions"
          label-cols-sm="3"
          label-cols-lg="3"
          label-class="pt-0"
        >
          <template #label>
            <span class="text-nowrap">Link Discuss-it
              <QuestionCircleTooltip id="link-discuss-it-questions-tooltip" /></span>
            <b-tooltip target="link-discuss-it-questions-tooltip" triggers="hover focus" delay="500">
              Linked Discuss-it questions share one discussion: all comments show in every linked assignment and
              students stay in the same group. Each assignment keeps its own settings and dates, and completion is
              based only on the comments made in that assignment. The copy will use the original's number of groups
              and question revision. You can link or unlink later from the question's Discuss-it settings.
            </b-tooltip>
          </template>
          <b-form-radio-group
            v-model="createAssignmentFromTemplateForm.link_discuss_it_questions"
            stacked
          >
            <b-form-radio value="1">
              Link Discuss-it questions with the same questions in the original assignment
            </b-form-radio>
            <b-form-radio value="0">
              Don't link them
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>
        <b-form-group
          v-if="clickerQuestionsExist && createAssignmentFromTemplateForm.level === 'properties_and_questions'"
          id="reset_clicker_settings_to_default"
          label-cols-sm="3"
          label-cols-lg="3"
          label-class="pt-0"
          label="Clicker Settings"
        >
          <b-form-radio-group
            v-model="createAssignmentFromTemplateForm.reset_clicker_settings_to_default"
            stacked
          >
            <b-form-radio value="0">
              Copy current settings
            </b-form-radio>
            <b-form-radio value="1">
              Reset settings to default settings
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>
        <b-form-group
          v-if="openEndedQuestionsInRealTimeAssignmentExist && createAssignmentFromTemplateForm.level === 'properties_and_questions'"
          id="remove_open_ended_questions_in_real_time_assignment_in_real_time_assignment"
          label-cols-sm="3"
          label-cols-lg="3"
          label-class="pt-0"
          label="Open-ended Questions"
        >
          <b-form-radio-group
            v-model="createAssignmentFromTemplateForm.remove_open_ended_questions_in_real_time_assignment"
            stacked
          >
            <b-form-radio value="0">
              Keep open-ended questions in this real time assignment
            </b-form-radio>
            <b-form-radio value="1">
              Remove open-ended questions from this real time assignment (recommended)
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>
        <template #modal-footer>
          <b-button
            size="sm"
            class="float-right"
            @click="$bvModal.hide('modal-create-assignment-from-template')"
          >
            Cancel
          </b-button>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="handleCreateAssignmentFromTemplate"
          >
            Yes, clone assignment!
          </b-button>
        </template>
      </b-modal>
      <b-modal
        id="modal-import-assignment"
        ref="modal"
        title="Import Assignment"
      >
        <b-container>
          <b-alert :show="Boolean(course.formative)">
            Only formative assignments and assignments from formative courses are available to import into this
            formative course.
          </b-alert>
          <b-row class="pb-4">
            <b-form-select id="collections"
                           v-model="collection"
                           :options="collectionOptions"
                           @change="importableCourse= null;importableAssignment=null;getCoursesByCollection($event)"
            />
          </b-row>
          <b-row class="pb-2">
            <b-form-select
              id="courses"
              v-model="importableCourse"
              :disabled="collection === null"
              :options="importableCourseOptions"
              @change="getImportableAssignments($event)"
            />
          </b-row>
          <b-row class="pb-4">
            <b-form-select
              id="assignments"
              v-model="importableAssignment"
              :disabled="importableCourse === null"
              :options="importableAssignmentOptions"
              @change="checkIfDiscussItOrClickerQuestionsExistsInAssignment($event)"
            />
          </b-row>
        </b-container>
        <b-form-group
          v-if="importableAssignment !== null"
          id="import_level"
          label-cols-sm="4"
          label-cols-lg="3"
          label="Import Level"
          label-for="Import Level"
        >
          <b-form-radio-group v-model="importAssignmentForm.level" stacked>
            <b-form-radio value="properties_and_questions">
              Properties and questions
            </b-form-radio>
            <b-form-radio value="properties_and_not_questions">
              Just the properties
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>
        <b-form-group
          v-show="course.lms && importableAssignment !== null"
          label-cols-sm="4"
          label-cols-lg="3"
          label-for="lms_grade_passback"
        >
          <template v-slot:label>
            LMS Grade Passback*
            <QuestionCircleTooltip :id="'lms_grade_passback_tooltip'" />
            <b-tooltip target="lms_grade_passback_tooltip"
                       delay="250"
                       triggers="hover focus"
            >
              With the automatic option, grades are passed back to your LMS each time that your one of your students
              submits their response to a question.
              For delayed grading, the manual option is recommended.
            </b-tooltip>
          </template>
          <b-form-radio-group
            v-model="importAssignmentForm.lms_grade_passback"
            required
            stacked
          >
            <b-form-radio name="lms_grade_passback" value="automatic">
              Automatic
            </b-form-radio>
            <b-form-radio name="lms_grade_passback" value="manual">
              Manual
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>
        <b-form-group
          v-if="discussItQuestionsExist && importAssignmentForm.level === 'properties_and_questions'"
          id="reset_clicker_settings_to_default"
          label-cols-sm="4"
          label-cols-lg="3"
          label="Clicker Settings"
        >
          <b-form-radio-group
            v-model="importAssignmentForm.reset_clicker_settings_to_default"
            stacked
          >
            <b-form-radio value="0">
              Copy current settings
            </b-form-radio>
            <b-form-radio value="1">
              Reset settings to default settings
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>

        <b-form-group
          v-if="openEndedQuestionsInRealTimeAssignmentExist && importAssignmentForm.level === 'properties_and_questions'"
          id="remove_open_ended_questions_in_real_time_assignment_in_real_time_assignment"
          label-cols-sm="3"
          label-cols-lg="2"
          label="Open-ended Questions"
        >
          <b-form-radio-group
            v-model="importAssignmentForm.remove_open_ended_questions_in_real_time_assignment"
            stacked
          >
            <b-form-radio value="0">
              Keep open-ended questions in this real time assignment
            </b-form-radio>
            <b-form-radio value="1">
              Remove open-ended questions from this real time assignment (recommended)
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>

        <template #modal-footer>
          <b-button
            size="sm"
            class="float-right"
            @click="$bvModal.hide('modal-import-assignment')"
          >
            Cancel
          </b-button>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="checkAssignmentAutoRelease"
          >
            Yes, import assignment!
          </b-button>
        </template>
      </b-modal>

      <b-modal
        id="modal-delete-assignment"
        ref="modal"
        :title="cannotDeleteAssignment ? 'Cannot Delete Assignment' :'Confirm Delete Assignment'"
        :hide-footer="cannotDeleteAssignment"
      >
        <b-alert :show="!tetheredBetaAssignmentExists && !linkedDiscussItDeleteCheck.can_delete" variant="danger">
          {{ linkedDiscussItDeleteCheck.cannot_delete_message }}
          You can always hide this assignment from your students instead.
        </b-alert>
        <div v-show="!cannotDeleteAssignment">
          <p>
            By deleting the assignment, you will also delete all student scores associated with the assignment.
          </p>
          <b-alert variant="danger" show>
            Once an assignment is deleted, it cannot be retrieved.
            <span v-if="hasLmsAssignmentId(assignmentId)">
              Please note that the assignment and all associated scores on your LMS will be deleted as well.
            </span>
            <template v-if="linkedDiscussItDeleteCheck.number_of_comments > 0">
              <p class="mt-2 mb-1">
                It will also remove {{ linkedDiscussItDeleteCheck.number_of_comments }} linked Discuss-it
                {{ linkedDiscussItDeleteCheck.number_of_comments === 1 ? 'comment' : 'comments' }} by
                {{ linkedDiscussItDeleteCheck.number_of_students }}
                {{ linkedDiscussItDeleteCheck.number_of_students === 1 ? 'student' : 'students' }}
                ({{ linkedDiscussItDeleteCheck.assignment_names.join(', ') }}).
              </p>
              <b-form-checkbox v-model="understandLinkedDiscussItCommentsRemoved">
                I understand these comments will be removed.
              </b-form-checkbox>
            </template>
          </b-alert>
        </div>
        <div v-show="tetheredBetaAssignmentExists">
          <p>
            Since this is an Alpha course and this assignment is already tethered to a Beta assignment, you cannot
            delete this assignment. However, you
            can always hide this
            assignment from your own students.
          </p>
        </div>
        <template #modal-footer>
          <b-button
            size="sm"
            class="float-right"
            @click="$bvModal.hide('modal-delete-assignment')"
          >
            Cancel
          </b-button>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            :disabled="linkedDiscussItDeleteCheck.number_of_comments > 0 && !understandLinkedDiscussItCommentsRemoved"
            @click="handleDeleteAssignment"
          >
            Yes, delete assignment!
          </b-button>
        </template>
      </b-modal>

      <b-modal
        id="modal-settings-overview"
        size="xl"
        dialog-class="settings-overview-dialog"
        ok-only
        ok-title="Close"
        button-size="sm"
      >
        <template #modal-header>
          <div class="d-flex align-items-center w-100">
            <h2 class="h5 modal-title mb-0 mr-1">
              Control Panel
            </h2>
            <ConsultInsight :url="'https://commons.libretexts.org/insight/control-panel'" class="mr-2" />
            <button type="button"
                    class="close ml-auto"
                    aria-label="Close"
                    @click="$bvModal.hide('modal-settings-overview')"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </template>
        <!-- This wrapper is the scroll area (both directions) so the two header rows can stay stuck to its top. -->
        <div class="control-panel-scroll">
          <table class="table table-sm table-striped settings-overview-table" aria-label="Control Panel">
            <thead>
            <tr>
              <th scope="col">
                <!-- each (?) is kept on the same line as the last word of its heading, so it never sits alone -->
                <span class="text-nowrap">Name <QuestionCircleTooltip :id="'cp-name-tooltip'" /></span>
                <b-tooltip target="cp-name-tooltip" delay="250" triggers="hover focus">
                  Each row is one assignment. Use the "Change all..." menus in the gray row to change a setting for
                  every assignment at once.
                </b-tooltip>
              </th>
              <th v-for="column in controlPanelReleaseColumns"
                  :key="`cp-header-${column.key}`"
                  scope="col"
                  class="overview-release-column"
              >
                <span class="text-nowrap">{{ column.label }} <QuestionCircleTooltip :id="`cp-${column.key}-tooltip`" /></span>
                <b-tooltip :target="`cp-${column.key}-tooltip`" delay="250" triggers="hover focus">
                  {{ column.tooltip }}
                </b-tooltip>
              </th>
              <th v-if="user.role === 2" scope="col" class="cp-group-start">
                Question <span class="text-nowrap">titles <QuestionCircleTooltip :id="'cp-titles-tooltip'" /></span>
                <b-tooltip target="cp-titles-tooltip" delay="250" triggers="hover focus">
                  Whether students see the titles of the questions.
                </b-tooltip>
              </th>
              <th v-if="user.role === 2" scope="col">
                Question URL <span class="text-nowrap">view <QuestionCircleTooltip :id="'cp-url-tooltip'" /></span>
                <b-tooltip target="cp-url-tooltip" delay="250" triggers="hover focus">
                  When you give students a direct link to a question, whether that link opens the whole assignment or
                  only that one question.
                </b-tooltip>
              </th>
              <th v-if="user.role === 2" scope="col">
                Student names to <span class="text-nowrap">graders <QuestionCircleTooltip :id="'cp-names-tooltip'" /></span>
                <b-tooltip target="cp-names-tooltip" delay="250" triggers="hover focus">
                  Whether your graders see your students' names. You can hide them to avoid any sort of conscious or
                  subconscious bias when grading.
                </b-tooltip>
              </th>
            </tr>
            <tr class="bulk-settings-row">
              <td>
                <span class="sr-only">Change all assignments</span>
              </td>
              <td v-for="column in bulkSettingColumns"
                  :key="`bulk-setting-${column.key}`"
                  :class="{ 'cp-group-start': column.key === 'question_titles_shown' }"
              >
                <b-form-select :key="`bulk-setting-select-${column.key}-${bulkSettingSelectKey}`"
                               :value="null"
                               size="sm"
                               :options="[{ value: null, text: 'Change all...' }, ...column.options]"
                               :aria-label="`Change ${column.label} for all assignments`"
                               :disabled="bulkSettingUpdating"
                               @change="initBulkSettingUpdate(column, $event)"
                />
              </td>
            </tr>
            </thead>
            <tbody>
            <tr v-for="assignment in assignmentsWithSettings" :key="`settings-overview-${assignment.id}`">
              <th scope="row">
                {{ assignment.name }}
              </th>
              <td v-for="item in getReleaseOverview(assignment)" :key="`release-overview-${assignment.id}-${item.key}`">
                <span v-if="item.text === 'N/A'" class="text-muted">N/A</span>
                <template v-else>
                  <ShowHideAssignmentProperties
                    :key="`overview-${item.key}-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                    :assignment="assignment"
                    :property="item.key"
                    :show-auto-unavailable-reason="false"
                    @refreshPage="getAssignments"
                  />
                  <div v-if="item.text !== 'Always released'" class="small text-muted mt-1 overview-release-date">
                    Release Date:
                    <AutoReleaseDate :key="`overview-date-${item.key}-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                                     :assignment="assignment"
                                     :property="item.dateKey"
                                     :auto-release-activated="+assignment[`auto_release_activated_${item.key}`]"
                    />
                  </div>
                </template>
              </td>
              <td v-if="user.role === 2" class="cp-group-start">
                <span v-if="isFormative(assignment)" class="text-muted">N/A</span>
                <ShowQuestionTitlesToggle
                  v-else
                  :key="`overview-titles-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                  :assignment="assignment"
                  :radio-buttons="true"
                />
              </td>
              <td v-if="user.role === 2">
                <QuestionUrlViewToggle
                  :key="`overview-url-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                  :assignment="assignment"
                  :radio-buttons="true"
                />
              </td>
              <td v-if="user.role === 2">
                <span v-if="isFormative(assignment)" class="text-muted">N/A</span>
                <GradersCanSeeStudentNamesToggle
                  v-else
                  :key="`overview-names-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                  :assignment="assignment"
                  :radio-buttons="true"
                />
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </b-modal>

      <b-modal
        id="modal-confirm-bulk-setting"
        :title="bulkSettingConfirmation.title"
        no-close-on-backdrop
        :no-close-on-esc="bulkSettingUpdating"
        :hide-header-close="bulkSettingUpdating"
        @hidden="bulkSettingSelectKey++"
      >
        <p v-if="bulkSettingConfirmation.changing.length">
          This will change <strong>{{ bulkSettingConfirmation.changing.length }}</strong>
          {{ bulkSettingConfirmation.changing.length === 1 ? 'assignment' : 'assignments' }}:
          {{ bulkSettingConfirmation.changing.map(assignment => assignment.name).join(', ') }}.
        </p>
        <p v-else>
          Every assignment already has this setting, so there is nothing to change.
        </p>
        <p v-if="bulkSettingConfirmation.alreadySet" class="small text-muted">
          {{ bulkSettingConfirmation.alreadySet }}
          {{ bulkSettingConfirmation.alreadySet === 1 ? 'assignment already has' : 'assignments already have' }}
          this setting.
        </p>
        <div v-if="bulkSettingConfirmation.skipped.length" class="small text-muted">
          Skipped:
          <ul class="mb-2">
            <li v-for="skipped in bulkSettingConfirmation.skipped" :key="`bulk-skipped-${skipped.id}`">
              {{ skipped.name }} ({{ skipped.reason }})
            </li>
          </ul>
        </div>
        <b-alert v-if="bulkSettingConfirmation.warning && bulkSettingConfirmation.changing.length"
                 show
                 variant="warning"
                 class="small"
        >
          {{ bulkSettingConfirmation.warning }}
        </b-alert>
        <template #modal-footer>
          <span v-if="bulkSettingUpdating" class="mr-auto small">
            <b-spinner small type="grow" />
            Updating {{ bulkSettingProgress }} of {{ bulkSettingConfirmation.changing.length }}...
          </span>
          <b-button size="sm" :disabled="bulkSettingUpdating" @click="$bvModal.hide('modal-confirm-bulk-setting')">
            Cancel
          </b-button>
          <b-button size="sm"
                    variant="primary"
                    :disabled="bulkSettingUpdating || !bulkSettingConfirmation.changing.length"
                    @click="applyBulkSettingUpdate"
          >
            Change {{ bulkSettingConfirmation.changing.length }}
            {{ bulkSettingConfirmation.changing.length === 1 ? 'assignment' : 'assignments' }}
          </b-button>
        </template>
      </b-modal>

      <b-modal
        id="modal-removed-discuss-it-comment-emails"
        title="Let Your Students Know"
        size="lg"
        ok-only
        ok-title="OK"
        button-size="sm"
      >
        <p>
          Discuss-it comments by these students were removed along with the assignment. You may want to let them
          know.
        </p>
        <p class="mb-0">
          Emails of students whose comments were removed:
          <span id="removed-discuss-it-comment-emails">{{ removedDiscussItCommentEmails.join(', ') }}</span>
          <a href=""
             aria-label="Copy student emails"
             @click.prevent="doCopy('removed-discuss-it-comment-emails')"
          >
            <font-awesome-icon :icon="copyIcon" />
          </a>
        </p>
      </b-modal>

      <b-container v-if="canViewAssignments" fluid class="px-0">
        <div v-show="course.lms_outage">
          <b-alert variant="warning" :show="true">
            <span class="font-weight-bold">LMS access is temporarily disabled for this course.</span>
            <p class="mb-1 mt-2">
              Your LMS is currently experiencing an outage, so this course's LMS connection has been temporarily
              turned off. In the meantime, please let your students know to go to
              <a href="https://adapt.libretexts.org" target="_blank">adapt.libretexts.org</a>, click
              <strong>Login/Register</strong>, then click <strong>Reset your password</strong>. Once they've reset
              their password, they'll be able to log in directly and complete their assignments.
            </p>
            <p class="mb-0">
              Grades will not be passed back to your LMS for any assignment a student hasn't already entered
              through your LMS at least once. Once LMS access is restored, you'll need to manually reconcile those
              grades yourself under <strong>Assignment Information</strong> and <strong>Assignment Gradebook</strong>.
            </p>
          </b-alert>
        </div>
        <div v-show="betaCoursesInfo.length>0">
          <b-alert variant="info" :show="true">
            <span class="font-weight-bold">
              This is an Alpha course with tethered Beta courses.  Any new assignments that are created in
              this course will be created in the associated Beta courses.
            </span>
          </b-alert>
        </div>
        <div v-show="lms">
          <div v-if="lmsError">
            <b-alert variant="danger" show>
              There was an error trying to connect to your LMS with ADAPT. Please reach out
              to support so that we can troubleshoot the issue:
              <br><br>{{ lmsError }}<br><br>
              In the meantime, you can still work on your ADAPT
              assignments.
            </b-alert>
          </div>
          <div v-else>
            <b-alert variant="info" :show="true">
              <p v-if="!course.lms_course_id">
                This is a course which is being served through your LMS. You will create your assignments
                in ADAPT including determining due dates, but will use your LMS's gradebook.
                <ConsultInsight v-if="user && user.role === 2 && course.lms"
                                :url="'https://commons.libretexts.org/insight/interfacing-adapt-to-lms-via-lti-and-api'"
                />
              </p>
              <p v-if="course.is_brightspace">
                LTI URL:
                <span id="lti-url"> https://adapt.libretexts.org/api/lti/redirect-uri</span> <a href=""
                                                                                                aria-label="Copy LTI URL"
                                                                                                @click.prevent="doCopy('lti-url')"
              >
                <font-awesome-icon :icon="copyIcon" />
              </a>
                <QuestionCircleTooltip :id="`lti-url-tooltip`" />
                <b-tooltip :target="`lti-url-tooltip`"
                           delay="250"
                           triggers="hover focus"
                >
                  Copy this URL into your Brightspace assignment in order to link your Brightspace assignment to your
                  ADAPT assignment.
                </b-tooltip>
              </p>
              <div v-if="course.lms_has_api_key && enableCanvasAPI">
                <div v-if="course.lms_course_id">
                  <div>
                    This course is directly linked to the LMS course <span class="font-weight-bold">{{ course.lms_course_name }}</span>.
                    <span class="pr-2"><b-button
                      v-b-tooltip="{ title: 'You can use this option if you accidentally linked your ADAPT course to an incorrect Canvas course.',delay: '500'}"
                      size="sm"
                      variant="info"
                      @click="$bvModal.show('modal-confirm-unlink-lms-course')"
                    >
                      Unlink Course
                    </b-button></span>
                    <b-button
                      v-show="false"
                      v-b-tooltip="{ title: 'Find Canvas assignments that have already been linked, i.e. ones with the (ADAPT) extension, and re-sync them to your ADAPT assignments.',delay: '500'}"
                      size="sm"
                      variant="primary"
                      :disabled="processingResync"
                      @click="reSyncLMSCourse"
                    >
                      <span v-show="processingResync">
                        <b-spinner small type="grow" />
                        Re-syncing Course
                      </span> <span v-show="!processingResync">
                        Re-sync Course
                      </span>
                    </b-button>
                  </div>
                  <div>
                    <a href="#" @click.prevent="$bvModal.show('modal-lms-linking-process')">Learn more about the
                      linking process.</a>
                  </div>
                  <b-modal id="modal-lms-linking-process"
                           title="Linking to your LMS"
                           hide-footer
                           size="lg"
                  >
                    <p>The following table describes how ADAPT and your LMS are linked.</p>
                    <table class="table table-striped">
                      <thead>
                      <tr>
                        <th>ADAPT</th>
                        <th>LMS</th>
                      </tr>
                      </thead>
                      <tr>
                        <td>
                          Assignment Name
                        </td>
                        <td>Assignment Name</td>
                      </tr>
                      <tr>
                        <td>
                          Assign Tos
                        </td>
                        <td>
                          Determines when students can access assignment questions. Currently just set for
                          "everybody".
                        </td>
                      </tr>
                      <tr>
                        <td>
                          Instructions
                        </td>
                        <td>Assignment Description</td>
                      </tr>
                      <tr>
                        <td>
                          Assignment Groups
                        </td>
                        <td>ADAPT assignment groups do not affect Canvas assignment groups</td>
                      </tr>
                      <tr>
                        <td>
                          Show/Hide
                        </td>
                        <td>Publish/Unpublish individual assignments</td>
                      </tr>
                      <tr>
                        <td>
                          Add/remove questions
                        </td>
                        <td>Total assignment points updated</td>
                      </tr>
                    </table>
                    <div v-if="unlinkedAssignments.length">
                      <b-card header-html="<h2 class=&quot;h7&quot;>Unlinked LMS assignments</h2>" class="mt-5">
                        <p>The following assignments were found in your LMS. However, they are not linked to ADAPT.</p>
                        <ol>
                          <li v-for="(unlinkedAssignment, unlinkedAssignmentsKey) in unlinkedAssignments"
                              :key="`unlinked-assignments-${unlinkedAssignmentsKey}`"
                          >
                            {{ unlinkedAssignment.name }}
                          </li>
                        </ol>
                      </b-card>
                    </div>
                  </b-modal>
                </div>
                <div v-else>
                  <div v-if="!course.lms_has_access_token">
                    <p class="mt-3">
                      To link your course to one of LMS courses, please
                      <GrantLmsApiAccess :key="`grant-lms-api-access-${course.id}`"
                                         :course-id="course.id"
                      />
                    </p>
                  </div>
                  <div v-if="course.lms_has_access_token">
                    <div v-show="lmsCourseOptions.length === 1" class="mt-3">
                      However, there are no LMS courses available to link to your ADAPT course.
                      If you would like to link this ADAPT course, please first unlink one of your other LMS courses
                      or create a new course in your LMS.
                    </div>
                    <div v-show="lmsCourseOptions.length > 1">
                      <p class="mt-3">
                        Please begin by choosing a course from your LMS to link to. Then, all assignments created within
                        ADAPT will automatically be created in your LMS with the structure "Some Name (ADAPT)". If an
                        assignment already exists in your LMS with the name
                        "Some Name (ADAPT)", then instead of creating a new assignment, ADAPT will link your ADAPT
                        assignment to the pre-existing LMS
                        assignment.
                      </p>
                      <b-form-group
                        v-show="!processingLinkCourseToLMS"
                        label-cols-sm="3"
                        label-cols-lg="2"
                        label-size="sm"
                        label="Link to LMS course"
                      >
                        <b-form-select
                          v-model="linkCourseToLMSForm.lms_course_id"
                          :options="lmsCourseOptions"
                          size="sm"
                          :class="{ 'is-invalid': linkCourseToLMSForm.errors.has('lms_course_id') }"
                          style="width: 200px"
                          @change="linkCourseToLMS"
                        />
                      </b-form-group>
                    </div>
                    <span v-if="processingLinkCourseToLMS" class="pl-2">
                      <b-spinner small type="grow" />
                      Processing...
                    </span>
                    <has-error :form="linkCourseToLMSForm" field="lms_course_id" />
                  </div>
                </div>
              </div>
            </b-alert>
            <div v-show="false && lms &&
                   course.lms_has_api_key
                   && enableCanvasAPI
                   && (course && (!course.updated_canvas_api.points || !course.updated_canvas_api.everybodys))"
                 class="mb-4"
            >
              <b-card show
                      variant="warning"
              >
                <div class="text-center">
                  <b-button
                    variant="outline-danger"
                    @click="showImportantCanvasUpdateMessage = !showImportantCanvasUpdateMessage"
                  >
                    {{ !showImportantCanvasUpdateMessage ? 'Show' : 'Hide' }} Important Canvas Update
                  </b-button>
                </div>
                <div v-show="showImportantCanvasUpdateMessage">
                  <p>As many of you know, this is the first semester where ADAPT is using the Canvas API. </p>
                  <ul>
                    <li v-show="!course.updated_canvas_api.points">
                      If you add/remove questions from an assignment in ADAPT, ADAPT will automatically adjust the
                      total points for the
                      assignment on Canvas. However, if you imported a course, ADAPT set the points to 100 by default
                      (this was not the
                      intention: we needed to consider the fact that the assignments were already populated with
                      questions). Some of you may have
                      manually adjusted your Canvas points to match your ADAPT points. However, if you would like,
                      ADAPT can automatically
                      update all assignments in your Canvas course to correctly match your ADAPT assignments and
                      update the grades that
                      were already passed back. Please note that you will only have to do this once and will not have
                      to do it
                      the next time you import an ADAPT course.
                      <b-button variant="primary" size="sm" @click="updateCanvasAssignments('points')">
                        Update Canvas points
                      </b-button>
                      <b-button variant="danger" size="sm" @click="alreadyUpdatedCanvas('points')">
                        I don't need to do this
                      </b-button>
                    </li>
                    <li v-show="!course.updated_canvas_api.everybodys">
                      <p>
                        Currently ADAPT is passing back the ADAPT start and end dates for your course for the
                        assignment unlock and due dates.
                        Note that ADAPT has ultimate control over whether a student can actually see the assignment
                        questions based on
                        how it's set up in ADAPT. What this means, is that currently, if your student enters an
                        assignment through Canvas and
                        it's not yet open in ADAPT, they won't be able to see the questions.
                        The ADAPT code has been updated so that ADAPT will update your Canvas "Everybody" to match
                        ADAPT's "Everybody" assign to.
                        If you are instead assigning by section or creating timing overrides, please 1) Create the
                        overrides in ADAPT 2) Manually update the
                        overrides in Canvas. If you would like ADAPT to automatically update all Canvas assignments on
                        a one-time basis so that
                        all ADAPT "Everybody" assign tos gets passed back to Canvas then you may do so now.
                      </p>
                      <p>
                        Regardless, in the future, saving any assignment will passback the current Everybody timing
                        back to Canvas.
                      </p>
                      <p>
                        <b-button variant="primary" size="sm" @click="updateCanvasAssignments('everybodys')">
                          Update Canvas Everybodys
                        </b-button>
                        <b-button variant="danger" size="sm" @click="alreadyUpdatedCanvas('everybodys')">
                          I don't need to do this
                        </b-button>
                      </p>
                    </li>
                  </ul>
                </div>
              </b-card>
            </div>
          </div>
        </div>
      </b-container>
      <div v-show="hasAssignments" ref="assignmentTableWrapper" class="table-responsive">
        <!--
          Find an assignment by typing part of its name; works together with the Group filter. Only shown once the
          course has enough assignments that you'd have to scroll to find one (see showAssignmentNameFilter).
        -->
        <b-input-group v-if="showAssignmentNameFilter" size="sm" class="mb-2 assignment-name-filter">
          <b-input-group-prepend is-text>
            <b-icon icon="search" aria-hidden="true" />
          </b-input-group-prepend>
          <b-form-input v-model="assignmentNameFilter"
                        type="search"
                        placeholder="Find an assignment by name"
                        aria-label="Find an assignment by name"
                        @keydown.esc="assignmentNameFilter = ''"
          />
        </b-input-group>
        <p v-if="showAssignmentNameFilter && assignmentNameFilter.trim() && !hasShownAssignments" class="small text-muted mb-2">
          No assignments<span v-if="chosenAssignmentGroup !== null"> in {{ chosenAssignmentGroupText }}</span> have
          "{{ assignmentNameFilter.trim() }}" in their name.
        </p>
        <div v-if="canFilterByAssignmentGroup && chosenAssignmentGroup !== null" class="mb-2">
          <span class="badge badge-pill badge-primary assignment-group-chip">
            Group: {{ chosenAssignmentGroupText }}
            <button type="button"
                    class="assignment-group-chip-clear"
                    :aria-label="`Clear the ${chosenAssignmentGroupText} group filter`"
                    @click="chooseAssignmentGroup(null)"
            >&times;</button>
          </span>
        </div>
        <div v-if="dateSort.key" class="mb-2 small text-muted">
          Sorted by {{ dateSortLabel }} ({{ dateSort.direction === 'asc' ? 'earliest first' : 'latest first' }}).
          Dragging to reorder is off while the list is sorted.
          <b-button variant="link" size="sm" class="p-0 align-baseline" @click="saveSortedOrder">
            Save this order
          </b-button>
          <span aria-hidden="true">|</span>
          <b-button variant="link" size="sm" class="p-0 align-baseline" @click="clearDateSort">
            Back to my order
          </b-button>
        </div>
        <table ref="assignmentTable"
               class="table assignment-table"
               :class="{ 'scrolls-sideways': tableScrollsSideways }"
               aria-label="Assignment List"
               style="table-layout: fixed; width: 100%;"
        >
          <thead>
          <tr>
            <th v-if="hasSettingsColumn" scope="col" style="width:64px" class="text-nowrap sticky-col sticky-col-first">
              <b-button v-if="assignmentsWithSettings.length"
                        id="settings-overview-button"
                        variant="link"
                        size="sm"
                        class="p-0 mr-2 assignment-settings-toggle"
                        aria-label="Control Panel for all assignments"
                        @click="openSettingsOverview"
              >
                <b-icon icon="sliders" />
              </b-button>
              <b-tooltip v-if="assignmentsWithSettings.length"
                         target="settings-overview-button"
                         delay="500"
                         triggers="hover focus"
              >
                Control Panel: see and change every assignment's release and visibility settings
              </b-tooltip>
              <b-button v-if="assignmentsWithSettings.length"
                        id="expand-all-settings-button"
                        variant="link"
                        size="sm"
                        class="p-0 assignment-settings-toggle"
                        :aria-label="allAssignmentSettingsExpanded ? 'Hide settings for all assignments' : 'Show settings for all assignments'"
                        @click="toggleAllAssignmentSettings"
              >
                <b-icon :icon="allAssignmentSettingsExpanded ? 'chevron-double-up' : 'chevron-double-down'" />
              </b-button>
              <b-tooltip v-if="assignmentsWithSettings.length"
                         target="expand-all-settings-button"
                         delay="500"
                         triggers="hover focus"
              >
                {{ allAssignmentSettingsExpanded ? 'Collapse all settings' : 'Expand all settings' }}
              </b-tooltip>
              <span v-else class="sr-only">Settings</span>
            </th>
            <th scope="col" :class="nameColumnStickyClass">
              <span class="text-nowrap">Name
                <QuestionCircleTooltip :id="'assignment-name-header-tooltip'" /></span>
              <b-tooltip target="assignment-name-header-tooltip"
                         delay="250"
                         triggers="hover focus"
              >
                Click a name to open that assignment's questions. Drag the &#8801; handle to change the order.<br><br>
                A lock means students have submitted, so only some properties can still be changed; a stopwatch means
                there is a time limit; a tree marks a learning tree assignment; and * means the assignment is not
                counted in the final weighted average.
              </b-tooltip>
            </th>
            <!-- 150px fits 90% of group names (16 characters) on one line; longer names wrap -->
            <th v-if="[2,4].includes(user.role)" scope="col" style="width:150px" class="text-nowrap d-none d-md-table-cell">
              <span class="text-nowrap">Group
                <QuestionCircleTooltip :id="'group-header-tooltip'" /></span>
              <b-tooltip target="group-header-tooltip"
                         delay="250"
                         triggers="hover focus"
              >
                The assignment group (for example, Homework or Exams) used to organize assignments and to weight
                the final average.<span v-if="canFilterByAssignmentGroup"> Use the funnel to show only one group.</span>
              </b-tooltip>
              <b-dropdown v-if="canFilterByAssignmentGroup"
                          id="assignment-group-filter"
                          variant="link"
                          size="sm"
                          no-caret
                          toggle-class="p-0 ml-1 assignment-group-filter-toggle"
                          :popper-opts="{ positionFixed: true }"
              >
                <template #button-content>
                  <b-icon :icon="chosenAssignmentGroup === null ? 'funnel' : 'funnel-fill'"
                          :class="chosenAssignmentGroup === null ? 'text-muted' : 'text-primary'"
                          aria-hidden="true"
                  />
                  <span class="sr-only">Filter by assignment group</span>
                </template>
                <b-dropdown-item v-for="option in assignmentGroupOptions"
                                 :key="`assignment-group-option-${option.value}`"
                                 :active="chosenAssignmentGroup === option.value"
                                 :aria-current="chosenAssignmentGroup === option.value ? 'true' : null"
                                 @click="chooseAssignmentGroup(option.value)"
                >
                  <b-icon icon="check"
                          class="mr-1"
                          :class="{ invisible: chosenAssignmentGroup !== option.value }"
                          aria-hidden="true"
                  />{{ option.text }}
                </b-dropdown-item>
              </b-dropdown>
              <b-tooltip v-if="canFilterByAssignmentGroup"
                         target="assignment-group-filter__BV_toggle_"
                         delay="500"
                         triggers="hover focus"
              >
                {{
                  chosenAssignmentGroup === null
                    ? 'Filter by assignment group'
                    : `Showing only ${chosenAssignmentGroupText}. Click to change the group filter.`
                }}
              </b-tooltip>
            </th>
            <th v-if="hasSettingsColumn" scope="col" style="width:200px">
              Released <span class="text-nowrap">Status
                <QuestionCircleTooltip :id="'release-badges-tooltip'" /></span>
              <b-tooltip target="release-badges-tooltip"
                         delay="250"
                         triggers="hover focus"
              >
                What students can currently see. <strong>Assignment</strong>: students can see and open the
                assignment. <strong>Scores</strong>, <strong>Solutions</strong>, <strong>Statistics</strong>: students
                can see their scores, the solutions, and the class statistics.<br><br>
                Checkmark: released. Cross: not released. Clock: scheduled to auto-release (hover for the date).
                Lock: can't be changed.<br><br>
                Click a badge to release or hide that item.
              </b-tooltip>
            </th>
            <th v-for="column in shownDateColumns"
                :key="`date-header-${column.key}`"
                scope="col"
                style="width:120px"
                :class="column.headerClass"
                :aria-sort="getAriaSort(column.key)"
            >
              <!--
                The sort control is an inline span with role="button" rather than a <button> or a link. A <button>
                is always laid out as one box, which pushed the (?) onto its own line; a link picks up the site's
                blue link color, which no page-level override reliably beat. A span wraps like plain text and has
                no link styling, so the header looks like the others. tabindex and the Enter/Space handlers give
                it the same keyboard behavior as a button. The outer span keeps the second word, the sort arrow
                and the (?) together.
              -->
              <span class="date-header-label">
                <span role="button"
                      tabindex="0"
                      class="date-sort-toggle"
                      :aria-label="`Sort by ${column.label}. ${getSortStateText(column.key)}`"
                      @click="toggleDateSort(column.key)"
                      @keydown.enter.prevent="toggleDateSort(column.key)"
                      @keydown.space.prevent="toggleDateSort(column.key)"
                >{{ column.label.split(' ')[0] }} <span class="text-nowrap">{{ column.label.split(' ')[1] }}&nbsp;<b-icon :icon="getSortIcon(column.key)"
                                                                                                                          :class="dateSort.key === column.key ? 'text-primary' : 'text-muted'"
                                                                                                                          font-scale="0.85"
                                                                                                                          aria-hidden="true"
                /></span></span>&nbsp;<QuestionCircleTooltip :id="`${column.key}-date-header-tooltip`" />
              </span>
              <b-tooltip :target="`${column.key}-date-header-tooltip`"
                         delay="250"
                         triggers="hover focus"
              >
                {{ column.tooltip }}<br><br>
                Click the heading to sort earliest first, again for latest first, and a third time to go back to your
                own order.
              </b-tooltip>
            </th>
            <th v-if="[2,4].includes(user.role)" scope="col" style="width:110px">
              Assignment <span class="text-nowrap">Status
                <span @mouseover="showAssignmentStatusModal()" @mouseout="mouseOverAssignmentStatus = false">
                  <QuestionCircleTooltip />
                </span></span>
            </th>
            <th scope="col" :style="lms ? 'width: 220px' :'width: 175px'">
              <span class="text-nowrap">Actions
                <QuestionCircleTooltip :id="'actions-header-tooltip'" /></span>
              <b-tooltip target="actions-header-tooltip"
                         delay="250"
                         triggers="hover focus"
              >
                <span v-if="lms">Link icon: the assignment's connection to your LMS (green when linked). </span>
                <span v-if="user.role !== 5">Check: open the grader (red when submissions still need grading). </span>
                <span v-if="[2,5].includes(user.role)">Gear: assignment properties. Copy: clone the assignment.
                  Trash: delete the assignment.</span>
              </b-tooltip>
            </th>
          </tr>
          </thead>
          <tbody v-for="assignment in displayedAssignments"
                 v-show="isAssignmentShown(assignment)"
                 :key="assignment.id"
                 class="assignment-tbody"
                 :data-assignment-id="assignment.id"
          >
          <tr :style="getAssignmentRowStyle(assignment)" class="assignment-row">
            <td v-if="hasSettingsColumn" class="text-center sticky-col sticky-col-first">
              <b-button v-if="canSeeSettings(assignment)"
                        variant="link"
                        size="sm"
                        class="p-0 assignment-settings-toggle"
                        :aria-expanded="isSettingsExpanded(assignment) ? 'true' : 'false'"
                        :aria-controls="`assignment-settings-${assignment.id}`"
                        :aria-label="`${isSettingsExpanded(assignment) ? 'Hide' : 'Show'} settings for ${assignment.name}`"
                        :title="`${isSettingsExpanded(assignment) ? 'Hide' : 'Show'} settings`"
                        @click="toggleAssignmentSettings(assignment)"
              >
                <b-icon :icon="isSettingsExpanded(assignment) ? 'chevron-down' : 'chevron-right'" />
              </b-button>
            </td>
            <th scope="row" :class="nameColumnStickyClass">
              <b-icon icon="list"
                      class="handle"
                      :class="{ 'handle-disabled': dateSort.key }"
                      :title="dateSort.key ? 'Go back to your own order to drag and reorder' : 'Drag to reorder'"
              />
              <a v-show="assignment.is_beta_assignment"
                 :id="getTooltipTarget('betaAssignment',assignment.id)"
                 href="#"
                 class="text-muted"
              >
                &beta;
              </a>
              <b-tooltip :target="getTooltipTarget('betaAssignment',assignment.id)"
                         delay="500"
                         triggers="hover focus"
              >
                This Beta assignment was automatically generated from its corresponding Alpha course. Because of the
                tethered
                nature, you cannot remove the assignment nor add/remove assessments.
              </b-tooltip>
              <a v-show="Boolean(course.alpha)"
                 :id="getTooltipTarget('alphaCourse',assignment.id)"
                 href="#"
                 class="text-muted"
              >&alpha; </a>
              <b-tooltip :target="getTooltipTarget('alphaCourse',assignment.id)"
                         delay="500"
                         triggers="hover focus"
              >
                This assignment is part of an Alpha course. Any assignments/assessments that you create or remove will
                be reflected in the tethered Beta courses.
              </b-tooltip>
              <span v-show="assignment.source === 'a'">
                  <span v-show="isLocked(assignment.has_submissions_or_file_submissions) && !isFormative (assignment)"
                        :id="getTooltipTarget('getQuestions',assignment.id)"
                  >
                    <b-icon
                      icon="lock-fill"
                    />
                  </span>
                </span>
              <b-icon
                v-if="assignment.assessment_type === 'learning tree'"
                icon="tree"
                variant="success"
              />
              <span v-if="assignmentTimeLimits(assignment).length" :id="`time-limit-tooltip-${assignment.id}`"
                    class="mr-1" style="color: #0f6674;" aria-label="This assignment has a time limit"
              >
                  <b-icon icon="stopwatch" font-scale="1.1" />
                </span>
              <b-tooltip v-if="assignmentTimeLimits(assignment).length" :target="`time-limit-tooltip-${assignment.id}`"
                         delay="250"
                         triggers="hover focus"
                         custom-class="time-limit-tooltip"
              >
                  <span v-if="assignmentTimeLimitSecondsList(assignment).length === 1">
                    Time limit: {{ formatSecondsAsHms(assignmentTimeLimitSecondsList(assignment)[0]) }}.
                  </span>
                <span v-else>
                    Time limit varies by assign-to group - click View for each group's timing.
                  </span>
              </b-tooltip>
              <router-link v-if="assignment.source !== 'x'"
                           :to="{ name: 'instructors.assignments.questions',params:{assignmentId:assignment.id}}"
              >
                {{ assignment.name }}
              </router-link>
              <a v-if="assignment.source === 'x'" href="" @click.prevent="showExternalAssignmentNoty()">{{ assignment.name }}</a> <span v-show="!assignment.include_in_weighted_average"
                                                                                                                                        :id="`not-shown-assignment-tooltip-${assignment.id}`" class="text-danger"
            >*</span>
              <b-tooltip :target="`not-shown-assignment-tooltip-${assignment.id}`"
                         delay="250"
                         triggers="hover focus"
              >
                {{ assignment.name }} will not be included when computing the final weighted average.
              </b-tooltip>
              <span v-if="user && [2,4].includes(user.role)">
                  <b-tooltip :target="getTooltipTarget('getQuestions',assignment.id)"
                             delay="500"
                             triggers="hover focus"
                  >
                    <div v-html="getLockedQuestionsMessage(assignment)" />
                  </b-tooltip>

                </span>
              <b-tooltip :target="getTooltipTarget('alphaCourse',assignment.id)"
                         delay="500"
                         triggers="hover focus"
              >
                This assignment is part of an Alpha course. Any assignments/assessments that you create or remove will
                be reflected in the tethered Beta courses.
              </b-tooltip>
              <span v-show="assignment.formative">
                  <b-tooltip :target="getTooltipTarget('formative-assignment',assignment.id)"
                             delay="500"
                             triggers="hover focus"
                  >
                    This is a formative assignment.  The solutions to these questions may be compromised.
                  </b-tooltip>
                  <a
                    :id="getTooltipTarget('formative-assignment',assignment.id)"
                    href="#"
                    class="text-muted"
                  ><b-icon-question-circle /></a>
                </span>
              <span v-show="assignment.assessment_type === 'clicker'">
                  <b-tooltip :target="getTooltipTarget('clicker-assignment',assignment.id)"
                             delay="500"
                             triggers="hover focus"
                  >
                    Clicker assignments are manually opened by instructors.  Available and due dates don't apply.<br><br>
                    If you would like your students to view all questions and solutions, you may do so by releasing the solutions to the assignment.
                  </b-tooltip>
                  <a
                    :id="getTooltipTarget('clicker-assignment',assignment.id)"
                    href="#"
                    class="text-muted"
                  ><b-icon-question-circle /></a>
                </span>
            </th>
            <td v-if="[2,4].includes(user.role)" class="d-none d-md-table-cell assignment-group-cell">
              <div v-if="isFormative (assignment)">
                N/A
              </div>
              <div v-if="!isFormative (assignment)">
                {{ assignment.assignment_group }}
              </div>
            </td>
            <td v-if="hasSettingsColumn">
              <div class="release-badges">
                <component :is="item.toggleable || item.note ? 'button' : 'span'"
                           v-for="item in getReleaseBadges(assignment)"
                           :key="`release-badge-${assignment.id}-${item.key}`"
                           :type="item.toggleable || item.note ? 'button' : null"
                           class="badge release-badge"
                           :class="[item.badgeClass, { 'release-badge-toggle': item.toggleable, 'release-badge-locked': item.note }]"
                           :disabled="item.toggleable && releaseBadgeUpdating === `${assignment.id}-${item.key}`"
                           :title="getReleaseBadgeTitle(item)"
                           @click="onReleaseBadgeClick(assignment, item)"
                >
                  <b-spinner v-if="releaseBadgeUpdating === `${assignment.id}-${item.key}`" small class="release-badge-spinner" />
                  <b-icon v-else :icon="item.icon" aria-hidden="true" />
                  {{ item.label }}
                  <b-icon v-if="item.trailingIcon" :icon="item.trailingIcon" class="ml-1" aria-hidden="true" />
                  <span class="sr-only">: {{ item.text }}. {{ getReleaseBadgeAction(item) }}</span>
                </component>
              </div>
            </td>
            <td v-if="[2,4].includes(user.role)" class="show_date d-none d-md-table-cell date-separator date-cell">
              <AutoReleaseDate :key="`auto-release-date-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                               :assignment="assignment"
                               :property="'show_date'"
                               :auto-release-activated="+assignment.auto_release_activated_shown"
              />
            </td>
            <td v-if="[2,4].includes(user.role)" class="date-separator-narrow date-cell">
              <div v-if="!showAssignTos(assignment)">
                N/A
              </div>
              <div v-if="showAssignTos(assignment)">
                  <span v-if="assignment.assign_tos.length === 1">
                    {{ $moment(assignment.assign_tos[0].available_from, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY') }}<br>
                    {{ $moment(assignment.assign_tos[0].available_from, 'YYYY-MM-DD HH:mm:ss A').format('h:mm A') }}
                  </span>
                <span v-if="assignment.assign_tos.length > 1">
                    <b-button variant="primary" size="sm" @click="viewAssignTos(assignment.assign_tos)">View</b-button>
                  </span>
              </div>
            </td>
            <td v-if="[2,4].includes(user.role)" class="date-cell">
              <div v-if="!showAssignTos(assignment)">
                N/A
              </div>
              <div v-if="showAssignTos(assignment)">
                  <span v-if="assignment.assign_tos.length === 1">
                    <span v-if="!showFinalSubmissionDeadline(assignment.assign_tos[0])">
                      {{ $moment(assignment.assign_tos[0].due, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY') }}<br>
                      {{ $moment(assignment.assign_tos[0].due, 'YYYY-MM-DD HH:mm:ss A').format('h:mm A') }}
                    </span>
                    <span v-if="showFinalSubmissionDeadline(assignment.assign_tos[0])">
                      {{
                        $moment(assignment.assign_tos[0].final_submission_deadline_date, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY')
                      }}<br>
                      {{
                        $moment(assignment.assign_tos[0].final_submission_deadline_time, 'HH:mm:ss A').format('h:mm A')
                      }}
                    </span>
                  </span>
                <span v-if="assignment.assign_tos.length > 1">
                    <b-button variant="primary" size="sm" @click="viewAssignTos(assignment.assign_tos)">View</b-button>
                  </span>
              </div>
            </td>
            <td v-if="[2,4].includes(user.role)">
              <div v-if="!showAssignTos(assignment)">
                N/A
              </div>
              <div v-if="showAssignTos(assignment)">
                  <span v-if="assignment.assign_tos.length === 1"
                        :class="getStatusTextClass(assignment.assign_tos[0].status)"
                  >{{ assignment.assign_tos[0].status }}</span>
                <span v-if="assignment.assign_tos.length > 1" v-html="assignment.overall_status" />
              </div>
            </td>
            <td>
              <div class="mb-0 text-nowrap">
                <b-tooltip :target="getTooltipTarget('linkToLMS',assignment.id)"
                           delay="500"
                           triggers="hover focus"
                >
                  <div v-if="!assignment.lms_resource_link_id">
                    {{ assignment.name }} is not currently linked to your LMS. Please visit your LMS to link this
                    assignment.
                  </div>
                  <div v-if="assignment.lms_resource_link_id">
                    <div v-if="assignment.lms_assignment_name">
                      The ADAPT assignment {{ assignment.name }} is linked to your LMS assignment
                      {{ assignment.lms_assignment_name }} in your LMS
                      course
                      {{ assignment.lms_course_name }}.
                    </div>
                    <div v-if="!assignment.lms_assignment_name">
                      This assignment is linked to an assignment in your LMS.
                    </div>
                    <div>
                      <br>If you have incorrectly linked this assignment, you can unlink it. Unlinking the ADAPT
                      assignment will
                      not delete your associated LMS assignment.
                    </div>
                  </div>
                </b-tooltip>

                <a v-show="course.lms && !assignment.lms_assignment_id"
                   :id="getTooltipTarget('linkToLMS',assignment.id)"
                   href=""
                   class="pr-1"
                   @click.prevent="unlinkAssignment(assignment)"
                >
                  <font-awesome-icon
                    :icon="linkIcon"
                    :class="assignment.lms_resource_link_id ? 'text-success' : 'dark-red'"
                    :aria-label="`Open Grader for ${assignment.name}`"
                  />
                </a>

                <b-tooltip :target="getTooltipTarget('viewSubmissionFiles',assignment.id)"
                           delay="500"
                           triggers="hover focus"
                >
                  <span v-show="assignment.num_to_grade === 0">Grading</span><span v-show="assignment.num_to_grade > 0">There are {{
                    assignment.num_to_grade
                  }}
                      submission<span v-show="assignment.num_to_grade >1">s</span> which still need to be graded.</span>
                </b-tooltip>
                <a v-if="user && user.role !== 5 && !isFormative (assignment)"
                   v-show="assignment.source === 'a' & assignment.submission_files !== '0'"
                   :id="getTooltipTarget('viewSubmissionFiles',assignment.id)"
                   href=""
                   class="pr-1"
                   @click.prevent="getSubmissionFileView(assignment.id, assignment.submission_files)"
                >
                  <b-icon-check-circle
                    :class="assignment.num_to_grade > 0 ? 'dark-red' : 'assignment-icon'"
                    :aria-label="`Open Grader for ${assignment.name}`"
                  />
                </a>
                <LMSGradePassback v-show="assignment.lms_grade_passback === 'manual'"
                                  :key="`LMSGradePassback-${assignment.id}`" :assignment="assignment"
                                  class="pr-2"
                />
                <span v-show="user && [2,5].includes(user.role)">
                    <b-tooltip :target="getTooltipTarget('editAssignmentProperties',assignment.id)"
                               delay="500"
                               triggers="hover focus"
                    >
                      Assignment Properties
                    </b-tooltip>
                    <a :id="getTooltipTarget('editAssignmentProperties',assignment.id)"
                       href=""
                       class="pr-1"
                       @click.prevent="initEditAssignmentProperties(assignment)"
                    >
                      <b-icon
                        icon="gear"
                        class="assignment-icon"
                        :aria-label="`Assignment properties for ${assignment.name}`"
                      />
                    </a>
                    <b-tooltip :target="getTooltipTarget('createAssignmentFromTemplate',assignment.id)"
                               triggers="hover focus"
                               delay="500"
                    >
                      Clone Assignment
                    </b-tooltip>
                    <a :id="getTooltipTarget('createAssignmentFromTemplate',assignment.id)"
                       href="#"
                       class="pr-1"
                       :aria-label="`Clone ${assignment.name}`"
                       @click="initCreateAssignmentFromTemplate(assignment)"
                    >
                      <font-awesome-icon
                        :icon="copyIcon"
                        class="assignment-icon"
                      />
                    </a>
                    <span v-show="false && course.lms_has_api_key && enableCanvasAPI">
                      <b-tooltip :target="getTooltipTarget('initResyncAssignment',assignment.id)"
                                 delay="500"
                                 triggers="hover focus"
                      >
                        Re-sync {{ assignment.name }} with Canvas
                      </b-tooltip>
                      <a :id="getTooltipTarget('initResyncAssignment',assignment.id)"
                         href=""
                         @click.prevent="initResyncAssignment(assignment)"
                      >
                        <b-icon icon="arrow-repeat" class="assignment-icon"
                                :aria-label="`Re-sync ${assignment.name} with Canvas`"
                        />
                      </a>
                    </span>
                    <b-tooltip :target="getTooltipTarget('deleteAssignment',assignment.id)"
                               delay="500"
                               triggers="hover focus"
                    >
                      Delete Assignment
                    </b-tooltip>
                    <a :id="getTooltipTarget('deleteAssignment',assignment.id)"
                       href=""
                       @click.prevent="deleteAssignment(assignment)"
                    >
                      <b-icon icon="trash" class="assignment-icon" :aria-label="`Delete ${assignment.name}`" />
                    </a>
                  </span>
              </div>
            </td>
          </tr>
          <tr v-if="isSettingsExpanded(assignment)"
              :id="`assignment-settings-${assignment.id}`"
              class="assignment-settings-row"
          >
            <td :colspan="numberOfTableColumns" class="p-0">
              <div class="assignment-settings px-3 py-3">
                <b-row>
                  <b-col :lg="hasVisibilitySettings(assignment) ? 6 : 12" class="mb-3 mb-lg-0">
                    <h3 class="h6 font-weight-bold mb-2">
                      Release to students
                      <QuestionCircleTooltip :id="`release-settings-tooltip-${assignment.id}`" />
                      <b-tooltip :target="`release-settings-tooltip-${assignment.id}`"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        <div v-html="getReleaseSettingsTooltip()" />
                      </b-tooltip>
                    </h3>
                    <table class="table table-sm mb-0 release-settings-table">
                      <thead>
                      <tr>
                        <th scope="col">
                          Item
                        </th>
                        <th scope="col">
                          Release
                        </th>
                        <th scope="col">
                          Release Date
                        </th>
                      </tr>
                      </thead>
                      <tbody>
                      <tr>
                        <th scope="row">
                          Assignment
                        </th>
                        <td>
                          <ShowHideAssignmentProperties
                            :key="`shown-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                            :assignment="assignment"
                            :property="'shown'"
                            @refreshPage="getAssignments"
                          />
                        </td>
                        <td>
                          <AutoReleaseDate :key="`auto-release-date-shown-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                                           :assignment="assignment"
                                           :property="'show_date'"
                                           :auto-release-activated="+assignment.auto_release_activated_shown"
                          />
                        </td>
                      </tr>
                      <tr v-if="!isFormative(assignment)">
                        <th scope="row">
                          Scores
                        </th>
                        <td>
                          <ShowHideAssignmentProperties
                            :key="`show-scores-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                            :assignment="assignment"
                            :property="'show_scores'"
                            @refreshPage="getAssignments"
                          />
                        </td>
                        <td>
                          <AutoReleaseDate :key="`auto-release-date-show-scores-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                                           :assignment="assignment"
                                           :property="'show_scores_date'"
                                           :auto-release-activated="+assignment.auto_release_activated_show_scores"
                          />
                        </td>
                      </tr>
                      <tr v-if="!isFormative(assignment)">
                        <th scope="row">
                          Solutions
                        </th>
                        <td>
                          <ShowHideAssignmentProperties
                            :key="`show-solutions-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                            :assignment="assignment"
                            :property="'solutions_released'"
                            @refreshPage="getAssignments"
                          />
                        </td>
                        <td>
                          <AutoReleaseDate :key="`auto-release-date-solutions-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                                           :assignment="assignment"
                                           :property="'solutions_released_date'"
                                           :auto-release-activated="+assignment.auto_release_activated_solutions_released"
                          />
                        </td>
                      </tr>
                      <tr v-if="!isFormative(assignment)">
                        <th scope="row">
                          Statistics
                        </th>
                        <td v-if="assignment.assessment_type === 'clicker'" colspan="2">
                          N/A for clicker assignments
                        </td>
                        <template v-else>
                          <td>
                            <ShowHideAssignmentProperties
                              :key="`students-can-view-assignment-statistics-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                              :assignment="assignment"
                              :property="'students_can_view_assignment_statistics'"
                              @refreshPage="getAssignments"
                            />
                          </td>
                          <td>
                            <AutoReleaseDate :key="`auto-release-date-statistics-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                                             :assignment="assignment"
                                             :property="'students_can_view_assignment_statistics_date'"
                                             :auto-release-activated="+assignment.auto_release_activated_students_can_view_assignment_statistics"
                            />
                          </td>
                        </template>
                      </tr>
                      </tbody>
                    </table>
                  </b-col>
                  <!-- Same card treatment as Release to students, so the two panels read as a pair -->
                  <b-col v-if="hasVisibilitySettings(assignment)" lg="6" class="visibility-settings">
                    <h3 class="h6 font-weight-bold mb-2">
                      Visibility
                      <QuestionCircleTooltip :id="`visibility-settings-tooltip-${assignment.id}`" />
                      <b-tooltip :target="`visibility-settings-tooltip-${assignment.id}`"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        What students see while working on the assignment, and what graders see while grading it.
                        Changes take effect right away.
                      </b-tooltip>
                    </h3>
                    <table class="table table-sm mb-0 release-settings-table visibility-settings-table">
                      <thead>
                      <tr>
                        <th scope="col">
                          Setting
                        </th>
                        <th scope="col">
                          Choice
                        </th>
                      </tr>
                      </thead>
                      <tbody>
                      <tr v-if="user.role === 2 && !isFormative(assignment)">
                        <th scope="row">
                          Question titles
                          <QuestionCircleTooltip :id="`question-titles-tooltip-${assignment.id}`" />
                          <b-tooltip :target="`question-titles-tooltip-${assignment.id}`"
                                     delay="500"
                                     triggers="hover focus"
                          >
                            Whether students see the titles of the questions.
                          </b-tooltip>
                        </th>
                        <td>
                          <ShowQuestionTitlesToggle
                            :key="`show-question-titles-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                            :assignment="assignment"
                            :radio-buttons="true"
                          />
                        </td>
                      </tr>
                      <tr v-if="user.role === 2">
                        <th scope="row">
                          Question URL view
                          <QuestionCircleTooltip :id="`question-url-view-tooltip-${assignment.id}`" />
                          <b-tooltip :target="`question-url-view-tooltip-${assignment.id}`"
                                     delay="500"
                                     triggers="hover focus"
                          >
                            You can provide your students with a URL taking them directly to any question in the
                            assignment, found within a given question's properties. From this question you can either
                            show the entire assignment or just limit the view to that specific question.
                          </b-tooltip>
                        </th>
                        <td>
                          <QuestionUrlViewToggle
                            :key="`question-url-view-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                            :assignment="assignment"
                            :radio-buttons="true"
                          />
                        </td>
                      </tr>
                      <tr v-if="user.role === 2 && !isFormative(assignment)">
                        <th scope="row">
                          Student names to graders
                          <QuestionCircleTooltip :id="`viewable-by-graders-tooltip-${assignment.id}`" />
                          <b-tooltip :target="`viewable-by-graders-tooltip-${assignment.id}`"
                                     delay="500"
                                     triggers="hover focus"
                          >
                            Whether your graders see your students' names. You can hide them to avoid any sort of
                            conscious or subconscious bias when grading.
                          </b-tooltip>
                        </th>
                        <td>
                          <GradersCanSeeStudentNamesToggle
                            :key="`graders-can-see-student-names-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                            :assignment="assignment"
                            :radio-buttons="true"
                          />
                        </td>
                      </tr>
                      </tbody>
                    </table>
                  </b-col>
                </b-row>
              </div>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!hasAssignments">
        <div class="mt-4">
          <b-alert :show="showNoAssignmentsAlert" variant="warning">
            <a href="#" class="alert-link">This course currently
              has
              no assignments.</a>
          </b-alert>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Form from 'vform'
import { mapGetters } from 'vuex'
import { getStatusTextClass } from '~/helpers/AssignTosStatus'
import {
  isLocked,
  getAssignments,
  isLockedMessage,
  initAssignmentGroupOptions,
  updateAssignmentGroupFilter
  , checkIfReleased
} from '~/helpers/Assignments'

import {
  initAddAssignment,
  editAssignmentProperties,
  getAssignmentGroups,
  prepareForm,
  assignmentForm,
  resetAssignmentForm
} from '~/helpers/AssignmentProperties'
import { updateModalToggleIndex } from '~/helpers/accessibility/fixCKEditor'
import AssignmentProperties from '~/components/AssignmentProperties'
import AssignTosToView from '~/components/AssignTosToView'

import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import Sortable from 'sortablejs'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { faLink } from '@fortawesome/free-solid-svg-icons'
import AllFormErrors from '~/components/AllFormErrors'
import ShowQuestionTitlesToggle from '~/components/ShowQuestionTitlesToggle.vue'
import GradersCanSeeStudentNamesToggle from '~/components/GradersCanSeeStudentNamesToggle'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import QuestionUrlViewToggle from '~/components/QuestionUrlViewToggle.vue'
import LMSGradePassback from '~/components/LMSGradePassback.vue'
import GrantLmsApiAccess from '~/components/GrantLmsApiAccess.vue'
import { isMobile } from '~/helpers/mobileCheck'
import ShowHideAssignmentProperties from '~/components/ShowHideAssignmentProperties.vue'
import AutoReleaseDate from '../../components/AutoReleaseDate.vue'
import ErrorMessage from '../../components/ErrorMessage.vue'
import ConsultInsight from '~/components/ConsultInsight.vue'
import { doCopy } from '../../helpers/Copy'
import consultInsight from '../../components/ConsultInsight.vue'
import QuestionCircleTooltip from '../../components/QuestionCircleTooltip.vue'
import { getTooltipTarget, initTooltips } from '../../helpers/Tooptips'

export default {
  middleware: 'auth',
  components: {
    QuestionCircleTooltip,
    ConsultInsight,
    ErrorMessage,
    AutoReleaseDate,
    ShowHideAssignmentProperties,
    GrantLmsApiAccess,
    LMSGradePassback,
    QuestionUrlViewToggle,
    Loading,
    AssignmentProperties,
    AssignTosToView,
    FontAwesomeIcon,
    AllFormErrors,
    GradersCanSeeStudentNamesToggle,
    ShowQuestionTitlesToggle
  },
  metaInfo () {
    return { title: `${this.course.name} - assignments` }
  },
  data: () => ({
    openEndedQuestionsInRealTimeAssignmentExist: false,
    clickerQuestionsExist: false,
    discussItQuestionsExist: false,
    errorMessage: '',
    canvasCourseUrlForm: new Form({ url: '', course_id: 0 }),
    showHideAssignmentPropertiesKey: 0,
    lmsError: '',
    assignmentToUnlink: {},
    importedAssignmentAutoRelease: {},
    nonMatchingAutoReleases: [],
    importableAssignmentAutoRelease: {},
    assignmentToResync: {},
    fields: [
      'canvas_assignment',
      {
        key: 'adapt_assignment',
        label: 'ADAPT assignment'
      }
    ],
    processingResync: false,
    resyncResults: [],
    showImportantCanvasUpdateMessage: false,
    mouseOverAssignmentStatus: false,
    unlinkedAssignments: [],
    enableCanvasAPI: false,
    savingAssignment: false,
    updateKey: 0,
    processingLinkAssignmentsToLMS: false,
    processingLinkCourseToLMS: false,
    linkCourseToLMSForm: new Form({
      lms_course_id: 0,
      lms_course_name: ''
    }),
    lmsCourseOptions: [],
    ownsAllQuestions: false,
    isFormativeAssignment: false,
    tetheredBetaAssignmentExists: false,
    linkedDiscussItDeleteCheck: { can_delete: true, number_of_comments: 0, number_of_students: 0, assignment_names: [], cannot_delete_message: '' },
    understandLinkedDiscussItCommentsRemoved: false,
    removedDiscussItCommentEmails: [],
    importableAssignment: null,
    importableAssignmentOptions: [{ value: null, text: 'Please choose an assignment' }],
    importableCourse: null,
    importableCourseOptions: [{ value: null, text: 'Please choose a course' }],
    collection: null,
    collectionOptions: [{ value: null, text: 'Please choose a collection' },
      { value: 'my_courses', text: 'My Courses' },
      { value: 'commons', text: 'Commons' },
      { value: 'all_public_courses', text: 'All Public Courses' }
    ],
    expandedAssignmentSettingIds: [],
    dateSort: { key: null, direction: null },
    tableScrollsSideways: false,
    isNarrowScreen: false,
    releaseBadgeUpdating: '',
    bulkSettingSelectKey: 0,
    bulkSettingUpdating: false,
    bulkSettingProgress: 0,
    bulkSettingConfirmation: { title: '', column: null, value: null, changing: [], skipped: [], alreadySet: 0, warning: '' },
    hasSubmissionsOrFileSubmissions: false,
    isDev: window.config.environment === 'dev',
    lms: false,
    isBetaAssignment: false,
    overallStatusIsNotOpen: false,
    copyIcon: faCopy,
    linkIcon: faLink,
    addAssignmentIsImport: false,
    isBetaCourse: false,
    betaCoursesInfo: [],
    allFormErrors: [],
    assignmentGroups: [],
    form: assignmentForm,
    assessmentType: '',
    chosenAssignmentGroupText: null,
    chosenAssignmentGroup: null,
    assignmentNameFilter: '',
    assignmentGroupOptions: [],
    createAssignmentFromTemplateForm: new Form({
      level: 'properties_and_questions',
      assign_to_groups: 1,
      link_discuss_it_questions: '1'
    }),
    createAssignmentFromTemplateAssignmentId: 0,
    createAssignmentFromTemplateAssignmentName: '',
    course: '',
    assignTosToView: [],
    currentOrderedAssignments: [],
    importAssignmentForm: new Form({
      level: 'properties_and_questions',
      lms_grade_passback: 'automatic'
    }),
    assignmentGroupForm: new Form({
      assignment_group: ''
    }),
    allAssignments: [],
    title: '',
    isLoading: false,
    solutionsReleased: 0,
    assignmentId: 0, // if there's an assignmentId it's an update
    assignments: [],
    completedOrCorrectOptions: [
      { item: 'correct', name: 'correct' },
      { item: 'completed', name: 'completed' }
    ],
    courseId: false,
    hasAssignments: false,
    has_submissions_or_file_submissions: false,
    canViewAssignments: false,
    showNoAssignmentsAlert: false
  }),
  computed: {
    bulkSettingColumns () {
      const release = [{ value: 1, text: 'Release all' }, { value: 0, text: 'Hide all' }]
      const visibility = [{ value: 1, text: 'Show all' }, { value: 0, text: 'Hide all' }]
      const columns = [
        { key: 'shown', label: 'Assignment', options: release, type: 'release' },
        { key: 'show_scores', label: 'Scores', options: release, type: 'release' },
        { key: 'solutions_released', label: 'Solutions', options: release, type: 'release' },
        { key: 'students_can_view_assignment_statistics', label: 'Statistics', options: release, type: 'release' }
      ]
      if (this.user && this.user.role === 2) {
        columns.push(
          { key: 'question_titles_shown', label: 'Question titles', options: visibility, type: 'visibility' },
          {
            key: 'question_url_view',
            label: 'Question URL view',
            options: [{ value: 'assignment', text: 'Assignment for all' }, { value: 'question', text: 'Question for all' }],
            type: 'url'
          },
          { key: 'graders_can_see_student_names', label: 'Student names to graders', options: visibility, type: 'visibility' }
        )
      }
      return columns
    },
    canFilterByAssignmentGroup () {
      return Boolean(this.user) && [2, 4].includes(this.user.role) && !this.course.formative &&
        this.assignmentGroupOptions.length > 1
    },
    controlPanelReleaseColumns () {
      // same order as getReleaseOverview(), which fills these columns. The Control Panel doesn't show a (?) next to
      // each greyed-out Auto box, so these header tooltips say when Auto is unavailable instead.
      const autoTimingNote = 'Auto-release timings are set in each assignment\'s properties (the gear icon).'
      return [
        {
          key: 'shown',
          label: 'Assignment',
          tooltip: 'Whether students can see and open the assignment. Manual releases it now; Auto releases it on its ' +
            'Release Date, which comes from the auto-release timing in the assignment\'s properties. Auto is greyed ' +
            'out for clicker assignments and for assignments with no auto-release timing for showing the assignment.'
        },
        {
          key: 'show_scores',
          label: 'Scores',
          tooltip: 'Whether students can see their scores. Scores are always released for real time and learning ' +
            'tree assignments since students get immediate feedback on each submission. Auto is greyed out for ' +
            `clicker assignments and when there's no auto-release timing for scores. ${autoTimingNote}`
        },
        {
          key: 'solutions_released',
          label: 'Solutions',
          tooltip: 'Whether students can see the solutions. Auto is greyed out for clicker assignments, when ' +
            'Solutions Availability is set to Automatic in the assignment\'s properties (solutions are then already ' +
            `shown automatically), and when there's no auto-release timing for solutions. ${autoTimingNote}`
        },
        {
          key: 'students_can_view_assignment_statistics',
          label: 'Statistics',
          tooltip: 'Whether students can see the class statistics for the assignment. Not available for clicker ' +
            `assignments. Auto is greyed out when there's no auto-release timing for statistics. ${autoTimingNote}`
        }
      ]
    },
    dateColumns () {
      return [
        {
          key: 'release',
          label: 'Release Date',
          // hidden on narrow screens, where the separator moves to Open Date
          headerClass: 'd-none d-md-table-cell date-separator',
          tooltip: 'When students can see the assignment. "Now" means it is already released, a date means it is ' +
            'scheduled to auto-release, and N/A means it has no auto-release date.'
        },
        {
          key: 'open',
          label: 'Open Date',
          headerClass: 'date-separator-narrow',
          tooltip: 'When students can start submitting. If different groups of students have different dates, ' +
            'click View in the row to see each one.'
        },
        {
          key: 'due',
          label: 'Due Date',
          headerClass: '',
          tooltip: 'When submissions are due. Once the due date passes, this shows the final submission deadline ' +
            'if late submissions are allowed.'
        }
      ]
    },
    shownDateColumns () {
      return this.user && [2, 4].includes(this.user.role) ? this.dateColumns : []
    },
    dateSortLabel () {
      const column = this.dateColumns.find(column => column.key === this.dateSort.key)
      return column ? column.label : ''
    },
    displayedAssignments () {
      // Sorting by a date only changes what's shown; the saved (drag-and-drop) order in this.assignments is untouched.
      if (!this.dateSort.key) {
        return this.assignments
      }
      const direction = this.dateSort.direction === 'desc' ? -1 : 1
      return this.assignments
        .map((assignment, index) => ({ assignment, index, value: this.getDateSortValue(assignment, this.dateSort.key) }))
        .sort((a, b) => {
          // N/A always goes to the bottom, whichever way the list is sorted
          if (a.value === null || b.value === null) {
            if (a.value === b.value) {
              return a.index - b.index
            }
            return a.value === null ? 1 : -1
          }
          if (a.value === b.value) {
            return a.index - b.index
          }
          return a.value < b.value ? -direction : direction
        })
        .map(item => item.assignment)
    },
    hasShownAssignments () {
      return this.displayedAssignments.some(assignment => this.isAssignmentShown(assignment))
    },
    showAssignmentNameFilter () {
      // About 6-8 rows fit on a screen, so past 10 you'd be scrolling to find one. Counts every assignment in the
      // course, not just the ones the filters leave showing. Stays visible while it has text in it, so it can't
      // vanish mid-search (e.g. right after deleting an assignment).
      const minimumAssignments = 10
      return this.assignments.length > minimumAssignments || Boolean(this.assignmentNameFilter)
    },
    hasSettingsColumn () {
      // The badge column and the settings arrow show for instructors and graders in every course, including formative
      // ones, since showing/hiding the assignment itself lives there now.
      return Boolean(this.user) && [2, 4].includes(this.user.role)
    },
    assignmentsWithSettings () {
      return this.displayedAssignments.filter(assignment => this.canSeeSettings(assignment))
    },
    allAssignmentSettingsExpanded () {
      return this.assignmentsWithSettings.length > 0 &&
        this.assignmentsWithSettings.every(assignment => this.isSettingsExpanded(assignment))
    },
    nameColumnStickyClass () {
      return this.hasSettingsColumn ? 'sticky-col sticky-col-name' : 'sticky-col sticky-col-first'
    },
    numberOfTableColumns () {
      // settings arrow + name + the instructor/grader columns + Released Status badges + actions
      // instructor/grader columns: Group, Release Date, Open Date, Due Date, Assignment Status
      // (Group and Release Date are hidden on narrow screens)
      const instructorOrGraderColumns = this.user && [2, 4].includes(this.user.role)
        ? (this.isNarrowScreen ? 3 : 5)
        : 0
      // the settings arrow and the Released Status badges only show together
      return (this.hasSettingsColumn ? 2 : 0) + 1 + instructorOrGraderColumns + 1
    },
    cannotDeleteAssignment () {
      return this.tetheredBetaAssignmentExists || !this.linkedDiscussItDeleteCheck.can_delete
    },
    consultInsight () {
      return consultInsight
    },
    ...mapGetters({
      user: 'auth/user'
    }),
    getAssignmentRowStyle () {
      return (assignment) => {
        let rowStyle = {}
        if (this.user.role === 2) {
          if (!assignment.shown) {
            rowStyle = { background: '#ffe8e7' }
            if (assignment.auto_release_activated_shown) {
              rowStyle = { background: '#FFEED7' }
            }
          } else {
            rowStyle = { background: '#E0F7E9' }
          }
          return rowStyle
        }
      }
    }
  },
  watch: {
    'dateSort.key' (key) {
      // the saved order can only be changed by dragging while the list is shown in that order
      if (this.assignmentSortable) {
        this.assignmentSortable.option('disabled', Boolean(key))
      }
    }
  },
  created () {
    this.courseId = this.$route.params.courseId
    this.isLocked = isLocked
    this.isLockedMessage = isLockedMessage
    this.initAssignmentGroupOptions = initAssignmentGroupOptions
    this.updateAssignmentGroupFilter = updateAssignmentGroupFilter
    this.resetAssignmentForm = resetAssignmentForm
    this.updateModalToggleIndex = updateModalToggleIndex
  },
  beforeDestroy () {
    window.removeEventListener('keydown', this.quickSave)
    if (this.narrowScreenQuery) {
      this.narrowScreenQuery.removeListener(this.updateIsNarrowScreen)
    }
    if (this.assignmentSortable) {
      this.assignmentSortable.destroy()
    }
    if (this.tableResizeObserver) {
      this.tableResizeObserver.disconnect()
    }
  },
  async mounted () {
    window.addEventListener('keydown', this.quickSave)
    // matches Bootstrap's d-md-* breakpoint used to hide Group and Release Date
    this.narrowScreenQuery = window.matchMedia('(max-width: 767.98px)')
    this.updateIsNarrowScreen()
    this.narrowScreenQuery.addListener(this.updateIsNarrowScreen)
    this.initAddAssignment = initAddAssignment
    this.editAssignmentProperties = editAssignmentProperties
    this.prepareForm = prepareForm
    initTooltips(this)
    this.isLoading = true
    if (!await this.getCourseInfo()) {
      this.isLoading = false
      return false
    }
    this.assignmentGroups = await getAssignmentGroups(this.courseId, this.$noty)
    if (this.user.role === 2) {
      await this.getAssignmentGroupFilter(this.courseId)
    }
    if (this.user) {
      if (![2, 4, 5].includes(this.user.role)) {
        this.isLoading = false
        this.$noty.error('You are not allowed to access this page.')
        return false
      }
      await this.getAssignments()
      this.currentOrderedAssignments = this.assignments
      this.$nextTick(() => {
        this.initAssignmentSortable()
        this.watchTableWidth()
      })
      this.initAssignmentGroupOptions(this.assignments)
      if (this.user.role === 2) {
        this.updateAssignmentGroupFilter(this.courseId)
      }
    }
  },
  methods: {
    initTooltips,
    getTooltipTarget,
    doCopy,
    getAssignments,
    isMobile,
    checkIfReleased,
    getStatusTextClass,
    submitManualCourseLink () {
      this.$bvModal.hide('modal-confirm-link-course')
      this.$bvModal.hide('modal-link-course-manually')
      this.linkCourseToLMS()
    },
    async validateLinkCourseByCanvasUrl () {
      try {
        const { data } = await this.canvasCourseUrlForm.post('/api/canvas-api/validate-course-url')
        if (data.type === 'success') {
          this.linkCourseToLMSForm = new Form({
            lms_course_id: data.lms_course_id,
            lms_course_name: data.lms_course_name
          })
          this.$bvModal.show('modal-confirm-link-course')
        } else {
          this.errorMessage = data.message
          this.allFormErrors = [this.errorMessage]
          this.$bvModal.show('modal-form-errors-canvas-url-form')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    toggleDateSort (key) {
      // earliest first -> latest first -> back to the saved order
      if (this.dateSort.key !== key) {
        this.dateSort = { key, direction: 'asc' }
      } else if (this.dateSort.direction === 'asc') {
        this.dateSort = { key, direction: 'desc' }
      } else {
        this.clearDateSort()
      }
    },
    clearDateSort () {
      this.dateSort = { key: null, direction: null }
    },
    async saveSortedOrder () {
      // make the sorted list the saved order (the same save dragging uses), then show it unsorted
      this.assignments = [...this.displayedAssignments]
      this.clearDateSort()
      await this.saveNewOrder()
    },
    getSortIcon (key) {
      if (this.dateSort.key !== key) {
        return 'arrow-down-up'
      }
      return this.dateSort.direction === 'asc' ? 'caret-up-fill' : 'caret-down-fill'
    },
    getAriaSort (key) {
      if (this.dateSort.key !== key) {
        return 'none'
      }
      return this.dateSort.direction === 'asc' ? 'ascending' : 'descending'
    },
    getSortStateText (key) {
      if (this.dateSort.key !== key) {
        return 'Not sorted.'
      }
      return this.dateSort.direction === 'asc' ? 'Sorted earliest first.' : 'Sorted latest first.'
    },
    toTimestamp (value, format = 'YYYY-MM-DD HH:mm:ss A') {
      if (!value) {
        return null
      }
      const date = this.$moment(value, format)
      return date.isValid() ? date.valueOf() : null
    },
    earliestTimestamp (timestamps) {
      const valid = timestamps.filter(timestamp => timestamp !== null)
      return valid.length ? Math.min(...valid) : null
    },
    getDueTimestamp (assignTo) {
      // matches the Due Date cell: after the due date it shows the final submission deadline
      if (this.showFinalSubmissionDeadline(assignTo)) {
        const day = this.$moment(assignTo.final_submission_deadline_date, 'YYYY-MM-DD HH:mm:ss A')
        const time = this.$moment(assignTo.final_submission_deadline_time, 'HH:mm:ss A')
        if (day.isValid() && time.isValid()) {
          return day.clone().hours(time.hours()).minutes(time.minutes()).seconds(time.seconds()).valueOf()
        }
      }
      return this.toTimestamp(assignTo.due)
    },
    getDateSortValue (assignment, key) {
      // null = N/A (always sorted last). Assignments with several assign-tos sort by their earliest date.
      switch (key) {
        case ('release'): {
          if (+assignment.shown) {
            // "Now" counts as the earliest release date
            return Number.NEGATIVE_INFINITY
          }
          const autoDate = assignment.auto_release_show_dates && assignment.auto_release_show_dates.show_date
          return autoDate && +assignment.auto_release_activated_shown ? this.toTimestamp(autoDate) : null
        }
        case ('open'):
          return this.showAssignTos(assignment) && assignment.assign_tos
            ? this.earliestTimestamp(assignment.assign_tos.map(assignTo => this.toTimestamp(assignTo.available_from)))
            : null
        case ('due'):
          return this.showAssignTos(assignment) && assignment.assign_tos
            ? this.earliestTimestamp(assignment.assign_tos.map(assignTo => this.getDueTimestamp(assignTo)))
            : null
      }
      return null
    },
    unlinkAssignment (assignment) {
      if (assignment.lms_resource_link_id) {
        this.assignmentToUnlink = assignment
        this.$bvModal.show('modal-unlink-lms-assignment')
      }
    },
    async handleUnlinkAssignment () {
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentToUnlink.id}/unlink-from-lms`)
        if (data.type !== 'error') {
          await this.getAssignments()
        }
        this.$noty[data.type](data.message)
        this.$bvModal.hide('modal-unlink-lms-assignment')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async reSyncAssignment () {
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentToResync.id}/resync-from-lms`)
        this.$noty[data.type](data.message)
        this.$bvModal.hide('modal-resync-assignment')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async initResyncAssignment (assignment) {
      this.assignmentToResync = assignment
      this.$bvModal.show('modal-resync-assignment')
    },
    async reSyncLMSCourse () {
      this.processingResync = true
      try {
        const { data } = await axios.patch(`/api/courses/${this.courseId}/resync-from-lms`)
        if (data.type === 'success') {
          this.resyncResults = data.resync_results
          await this.getAssignments()
          this.$bvModal.show('modal-resync-results')
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processingResync = false
    },
    showAssignTos (assignment) {
      return !this.isFormative(assignment) && assignment.assessment_type !== 'clicker'
    },
    assignmentTimeLimits (assignment) {
      if (!assignment.assign_tos || !assignment.assign_tos.length) {
        return []
      }
      const limits = assignment.assign_tos
        .map(assignTo => assignTo.time_limit)
        .filter(timeLimit => Boolean(timeLimit))
      return [...new Set(limits)]
    },
    assignmentTimeLimitSecondsList (assignment) {
      if (!assignment.assign_tos || !assignment.assign_tos.length) {
        return []
      }
      const seconds = assignment.assign_tos
        .map(assignTo => assignTo.time_limit_seconds)
        .filter(timeLimitSeconds => Boolean(timeLimitSeconds))
      return [...new Set(seconds)]
    },
    formatSecondsAsHms (totalSeconds) {
      const hours = Math.floor(totalSeconds / 3600)
      const minutes = Math.floor((totalSeconds % 3600) / 60)
      const seconds = totalSeconds % 60
      const parts = []
      if (hours > 0) parts.push(`${hours} hour${hours === 1 ? '' : 's'}`)
      if (minutes > 0) parts.push(`${minutes} minute${minutes === 1 ? '' : 's'}`)
      if (seconds > 0 || parts.length === 0) parts.push(`${seconds} second${seconds === 1 ? '' : 's'}`)
      return parts.join(', ')
    },
    async alreadyUpdatedCanvas (property) {
      try {
        const { data } = await axios.patch(`/api/canvas-api/course/${this.courseId}/${property}/already-updated`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.course.updated_canvas_api[property] = true
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateCanvasAssignments (property) {
      try {
        const { data } = await axios.post(`/api/canvas-api/course/${this.courseId}/${property}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.course.updated_canvas_api[property] = true
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    showAssignmentStatusModal () {
      this.mouseOverAssignmentStatus = true
      setTimeout(() => {
        if (this.mouseOverAssignmentStatus) {
          this.$bvModal.show('modal-assignment-status')
        }
      }, 500)
    },
    async linkCourseToLMS () {
      if (!this.linkCourseToLMSForm.lms_course_id) {
        return
      }
      if (this.linkCourseToLMSForm.lms_course_id === -1) {
        this.canvasCourseUrlForm = new Form({
          url: '',
          course_id: this.courseId
        })
        this.linkCourseToLMSForm = new Form({
          lms_course_id: 0,
          lms_course_name: ''
        })
        this.errorMessage = ''
        this.$bvModal.show('modal-link-course-manually')
        return false
      }
      this.processingLinkCourseToLMS = true
      try {
        const { data } = await this.linkCourseToLMSForm.patch(`/api/courses/${this.courseId}/link-to-lms`)
        if (data.type === 'success') {
          this.unlinkedAssignments = data.unlinked_assignments
          await this.getCourseInfo()
          await this.linkAssignmentsToLMS()
        } else {
          this.$noty[data.type](data.message)
          this.linkCourseToLMSForm.lms_course_id = 0
        }
      } catch (error) {
        this.$noty.error(error.message)
        this.linkCourseToLMSForm.lms_course_id = 0
      }
      this.processingLinkCourseToLMS = false
    },

    async linkAssignmentsToLMS () {
      for (let i = 0; i < this.assignments.length; i++) {
        this.assignments[i].link_to_lms = { message: 'Pending', type: '', message_class: 'text-warning' }
      }
      this.$bvModal.show('modal-link-assignments-to-lms')
      if (this.assignments.length) {
        this.processingLinkAssignmentsToLMS = true
      }
      for (let i = 0; i < this.assignments.length; i++) {
        let assignment = this.assignments[i]
        try {
          const { data } = await axios.patch(`/api/assignments/${assignment.id}/link-to-lms`, { unlinked_assignments: this.unlinkedAssignments })
          this.assignments[i].link_to_lms =
            {
              message: data.message,
              type: data.type,
              message_class: data.type === 'success' ? 'text-success' : 'text-danger'
            }
        } catch (error) {
          this.assignments[i].link_to_lms =
            {
              message: error.message,
              type: 'error'
            }
        }
        this.updateKey++
      }
      this.processingLinkAssignmentsToLMS = false

      /* START:  Linking works but why doesn't it show up in the table?
        How to get the initial key?
        Be able to link/unlink manually each assignment
What assignment parameters??? */
    },
    async unlinkCourseFromLMS () {
      try {
        const { data } = await axios.patch(`/api/courses/${this.courseId}/unlink-from-lms`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.$bvModal.hide('modal-confirm-unlink-lms-course')
          await this.getCourseInfo()
          this.linkCourseToLMSForm.lms_course_id = 0
          this.$forceUpdate()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    hasLmsAssignmentId (assignmentId) {
      const assignment = this.assignments.find(assignment => assignment.id === assignmentId)
      return assignment && assignment.lms_assignment_id
    },
    showFinalSubmissionDeadline (assignTo) {
      return assignTo.final_submission_deadline && this.$moment().isAfter(this.$moment(assignTo.due))
    },
    isFormative (assignment) {
      return assignment.formative || this.course.formative
    },
    quickSave (event) {
      if (event.ctrlKey && event.key === 'S') {
        this.handleSubmitAssignmentInfo()
      }
    },
    initEditAssignmentProperties (assignment) {
      this.assignmentId = assignment.id
      this.isFormativeAssignment = assignment.formative
      this.ownsAllQuestions = assignment.owns_all_questions
      editAssignmentProperties(assignment, this)
    },
    populateFormWithAssignmentTemplate (assignmentProperties) {
      assignmentProperties.is_template = true
      assignmentProperties.modal_already_shown = true
      editAssignmentProperties(assignmentProperties, this)
    },
    async getImportableAssignments (course) {
      let url
      switch (this.collection) {
        case ('my_courses'):
          url = `/api/assignments/courses/${course}`
          break
        case ('all_public_courses'):
          url = `/api/assignments/courses/public/${course}/names`
          break
        case ('commons'):
          url = `/api/assignments/open/commons/${course}`
          break
        default:
          alert(`${this.collection} does not exist.  Please contact us.`)
          return false
      }
      try {
        const { data } = await axios.get(url)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }

        for (let i = 0; i < data.assignments.length; i++) {
          this.importableAssignmentOptions = [{ value: null, text: `Please choose an assignment` }]
          if (data.assignments) {
            for (let i = 0; i < data.assignments.length; i++) {
              const assignment = data.assignments[i]
              if (this.course.formative && !assignment.formative && !assignment.in_formative_course) {
                continue
              }
              this.importableAssignmentOptions.push({ value: assignment.id, text: assignment.name })
            }
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getCoursesByCollection (collection) {
      if (!collection) {
        this.$noty.info('Please first choose a collection')
        return false
      }
      try {
        let url
        let collectionName
        switch (collection) {
          case ('commons'):
            url = '/api/courses/commons'
            collectionName = 'commons_courses'
            break
          case ('my_courses'):
            collectionName = 'courses'
            url = '/api/courses'
            break
          case ('all_public_courses'):
            collectionName = 'public_courses'
            url = '/api/courses/public'
            break
        }

        const { data } = await axios.get(url)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        console.error(data)
        this.importableCourseOptions = [{ value: null, text: `Please choose a course` }]
        if (data[collectionName]) {
          let importableCourseOptions = []
          for (let i = 0; i < data[collectionName].length; i++) {
            let course = data[collectionName][i]
            if (this.course.formative && !(course.formative || course.has_at_least_one_formative_assignment)) {
              continue
            }
            let text = course.name
            if (collection === 'all_public_courses' && course.instructor === 'Commons Instructor') {
              continue
            }
            if (['all_public_courses', 'my_courses'].includes(collection)) {
              if (course.term) {
                text += ` (${course.term})`
              }
            }
            if (collection === 'all_public_courses') {
              text += ` --- ${course.instructor}`
              if (course.school !== 'Not Specified') {
                text += `/${course.school}`
              }
            }
            importableCourseOptions.push({ value: course.id, text: text })
          }
          importableCourseOptions.sort((a, b) => (a.text.toUpperCase() > b.text.toUpperCase()) ? 1 : -1)
          this.importableCourseOptions = this.importableCourseOptions.concat(importableCourseOptions)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    updateIsNarrowScreen () {
      this.isNarrowScreen = this.narrowScreenQuery.matches
    },
    watchTableWidth () {
      // The divider after the pinned Name column only means something when the table actually scrolls sideways,
      // so it's shown only then. Re-checked whenever the wrapper or the table changes size.
      const wrapper = this.$refs.assignmentTableWrapper
      const table = this.$refs.assignmentTable
      if (!wrapper || !table || this.tableResizeObserver) {
        return
      }
      this.updateTableScrollsSideways()
      if (window.ResizeObserver) {
        this.tableResizeObserver = new ResizeObserver(() => this.updateTableScrollsSideways())
        this.tableResizeObserver.observe(wrapper)
        this.tableResizeObserver.observe(table)
      }
    },
    updateTableScrollsSideways () {
      const wrapper = this.$refs.assignmentTableWrapper
      // 1px of slack so rounding doesn't make it flicker on and off
      this.tableScrollsSideways = Boolean(wrapper) && wrapper.scrollWidth > wrapper.clientWidth + 1
    },
    isAssignmentShown (assignment) {
      // the Group filter and the name search both have to match
      const groupMatches = this.chosenAssignmentGroup === null ||
        assignment.assignment_group === this.chosenAssignmentGroupText
      const search = this.assignmentNameFilter.trim().toLowerCase()
      const nameMatches = !search || !this.showAssignmentNameFilter ||
        String(assignment.name).toLowerCase().includes(search)
      return groupMatches && nameMatches
    },
    chooseAssignmentGroup (value) {
      this.chosenAssignmentGroup = value
      this.updateAssignmentGroupFilter(this.courseId)
    },
    initAssignmentSortable () {
      // Each assignment is its own <tbody> (its row plus its settings row), so the table is sorted with
      // SortableJS directly: vuedraggable picks the wrong item when the table header lives inside it.
      if (this.assignmentSortable || !this.$refs.assignmentTable) {
        return
      }
      this.assignmentSortable = Sortable.create(this.$refs.assignmentTable, {
        handle: '.handle',
        draggable: '.assignment-tbody',
        disabled: Boolean(this.dateSort.key),
        onStart: () => {
          this.assignmentOrderBeforeDrag = this.getAssignmentTbodies().map(tbody => +tbody.dataset.assignmentId)
        },
        onEnd: this.onAssignmentDragEnd
      })
    },
    getAssignmentTbodies () {
      return Array.from(this.$refs.assignmentTable.children)
        .filter(element => element.classList.contains('assignment-tbody'))
    },
    onAssignmentDragEnd () {
      const tbodies = this.getAssignmentTbodies()
      const newOrder = tbodies.map(tbody => +tbody.dataset.assignmentId)
      if (newOrder.join() === this.assignmentOrderBeforeDrag.join()) {
        return
      }
      // Put the rows back where Vue rendered them, then let Vue re-render from the reordered list.
      const tbodyById = {}
      tbodies.forEach(tbody => {
        tbodyById[tbody.dataset.assignmentId] = tbody
      })
      this.assignmentOrderBeforeDrag.forEach(id => this.$refs.assignmentTable.appendChild(tbodyById[id]))
      this.assignments = newOrder.map(id => this.assignments.find(assignment => +assignment.id === id))
      this.saveNewOrder()
    },
    getBulkSettingSkipReason (assignment, key) {
      // showing the assignment and the question URL view apply to formative assignments too
      if (!['shown', 'question_url_view'].includes(key) && this.isFormative(assignment)) {
        return 'formative assignment'
      }
      if (key === 'show_scores' && ['real time', 'learning tree'].includes(assignment.assessment_type)) {
        return `scores are always released for ${assignment.assessment_type} assignments`
      }
      if (key === 'students_can_view_assignment_statistics' && assignment.assessment_type === 'clicker') {
        return 'not available for clicker assignments'
      }
      return ''
    },
    bulkSettingHasValue (assignment, column, value) {
      return column.type === 'url'
        ? (assignment.question_url_view || 'assignment') === value
        : Boolean(+assignment[column.key]) === Boolean(+value)
    },
    initBulkSettingUpdate (column, value) {
      if (value === null) {
        return
      }
      const option = column.options.find(option => option.value === value)
      const changing = []
      const skipped = []
      let alreadySet = 0
      for (const assignment of this.assignmentsWithSettings) {
        const reason = this.getBulkSettingSkipReason(assignment, column.key)
        if (reason) {
          skipped.push({ id: assignment.id, name: assignment.name, reason })
        } else if (this.bulkSettingHasValue(assignment, column, value)) {
          alreadySet++
        } else {
          changing.push(assignment)
        }
      }
      let warning = ''
      if (column.key === 'shown' && !+value) {
        warning = 'Students will no longer be able to see or open these assignments until you release them again.'
      }
      if (column.key === 'solutions_released' && +value) {
        warning = 'Students will be able to see the solutions right away, including for real time assignments set to ' +
          'show solutions automatically, and any individually hidden clicker solutions will be released too.'
      }
      if (column.key === 'students_can_view_assignment_statistics' && +value &&
        changing.some(assignment => !+assignment.show_scores)) {
        warning = 'Some of these assignments don\'t show students their own scores yet, but the statistics will be shown anyway.'
      }
      this.bulkSettingConfirmation = {
        title: `${column.label}: ${option ? option.text : ''}`,
        column,
        value,
        changing,
        skipped,
        alreadySet,
        warning
      }
      this.bulkSettingProgress = 0
      this.$bvModal.show('modal-confirm-bulk-setting')
    },
    getBulkSettingRequestUrl (assignment, key) {
      // These endpoints flip the current value, so they are only called for assignments that need to change.
      switch (key) {
        case ('shown'):
          return `/api/assignments/${assignment.id}/show-assignment`
        case ('show_scores'):
          return `/api/assignments/${assignment.id}/show-scores`
        case ('solutions_released'):
          return `/api/assignments/${assignment.id}/solutions-released`
        case ('students_can_view_assignment_statistics'):
          return `/api/assignments/${assignment.id}/show-assignment-statistics`
        case ('question_titles_shown'):
          return `/api/assignments/${assignment.id}/show-question-titles`
        case ('graders_can_see_student_names'):
          return `/api/assignments/${assignment.id}/graders-can-see-student-names/${Number(+assignment.graders_can_see_student_names)}`
        case ('question_url_view'):
          return `/api/assignments/${assignment.id}/question-url-view`
      }
      return ''
    },
    async applyBulkSettingUpdate () {
      const { column, changing } = this.bulkSettingConfirmation
      this.bulkSettingUpdating = true
      this.bulkSettingProgress = 0
      const failures = []
      for (const assignment of changing) {
        this.bulkSettingProgress++
        try {
          const { data } = await axios.patch(this.getBulkSettingRequestUrl(assignment, column.key), { deactivate_auto_release: 0 })
          if (data.type === 'error') {
            failures.push(`${assignment.name}: ${data.message}`)
          }
        } catch (error) {
          failures.push(`${assignment.name}: ${error.message}`)
        }
      }
      await this.getAssignments()
      this.bulkSettingUpdating = false
      this.$bvModal.hide('modal-confirm-bulk-setting')
      const updated = changing.length - failures.length
      if (updated) {
        this.$noty.success(`${column.label} updated for ${updated} ${updated === 1 ? 'assignment' : 'assignments'}.`)
      }
      if (failures.length) {
        this.$noty.error(`Could not update ${failures.length} ${failures.length === 1 ? 'assignment' : 'assignments'}:<br>${failures.join('<br>')}`, { timeout: 12000 })
      }
    },
    openSettingsOverview () {
      // The overview reuses the same controls as the row panels, and those controls carry per-assignment ids,
      // so close any open panels first to keep each control on the page only once.
      this.expandedAssignmentSettingIds = []
      this.$nextTick(() => this.$bvModal.show('modal-settings-overview'))
    },
    getReleaseOverview (assignment) {
      // Mirrors what the settings panel shows: released now, scheduled to auto-release, or not released.
      // This order fills the Control Panel's release columns (controlPanelReleaseColumns).
      const items = [
        { key: 'shown', dateKey: 'show_date', label: 'Assignment' },
        { key: 'show_scores', dateKey: 'show_scores_date', label: 'Scores' },
        { key: 'solutions_released', dateKey: 'solutions_released_date', label: 'Solutions' },
        { key: 'students_can_view_assignment_statistics', dateKey: 'students_can_view_assignment_statistics_date', label: 'Statistics' }
      ]
      const released = 'badge-success'
      const scheduled = 'badge-light border border-primary text-primary'
      const notReleased = 'badge-danger'
      return items.map(({ key, dateKey, label }) => {
        const notApplicable = { key, dateKey, label, text: 'N/A', class: 'text-muted', icon: '', badgeClass: '' }
        // formative assignments can still be shown or hidden, but have no scores, solutions or statistics to release
        if (key !== 'shown' && this.isFormative(assignment)) {
          return notApplicable
        }
        if (key === 'students_can_view_assignment_statistics' && assignment.assessment_type === 'clicker') {
          return notApplicable
        }
        if (key === 'show_scores' && ['real time', 'learning tree'].includes(assignment.assessment_type)) {
          // the checkmark still says "released"; the lock after the label says it can't be changed
          return {
            key,
            dateKey,
            label,
            text: 'Always released',
            class: 'text-success',
            icon: 'check-circle',
            trailingIcon: 'lock-fill',
            badgeClass: released,
            toggleable: false,
            note: `Scores are always released for ${assignment.assessment_type} assignments since students get immediate feedback on each submission, so they can't be hidden.`
          }
        }
        if (+assignment[key]) {
          return { key, dateKey, label, text: 'Released', class: 'text-success', icon: 'check-circle', badgeClass: released, toggleable: true }
        }
        const autoDate = assignment.auto_release_show_dates && assignment.auto_release_show_dates[dateKey]
        if (autoDate && +assignment[`auto_release_activated_${key}`]) {
          const formatted = this.$moment(autoDate, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY h:mm A')
          return { key, dateKey, label, text: `Auto-release ${formatted}`, class: 'text-primary', icon: 'clock', badgeClass: scheduled, toggleable: true }
        }
        return { key, dateKey, label, text: 'Not released', class: 'text-muted', icon: 'x-circle', badgeClass: notReleased, toggleable: true }
      })
    },
    onReleaseBadgeClick (assignment, item) {
      if (item.toggleable) {
        this.toggleReleaseFromBadge(assignment, item)
      } else if (item.note) {
        this.$noty.info(item.note)
      }
    },
    getReleaseBadgeAction (item) {
      if (item.note) {
        return item.note
      }
      if (!item.toggleable) {
        return ''
      }
      return item.text === 'Released' ? 'Click to hide.' : 'Click to release now.'
    },
    getReleaseBadgeTitle (item) {
      return `${item.label}: ${item.text}. ${this.getReleaseBadgeAction(item)}`.trim()
    },
    async getReleaseBadgeConfirmation (assignment, key, release) {
      // Same cases the Manual checkboxes ask about before changing anything.
      let message = ''
      if (key === 'show_scores' && !release && assignment.assessment_type !== 'delayed') {
        message = `This is a "${assignment.assessment_type}" assignment. Typically for this type of assignment, students get immediate feedback. Are you sure that you want to hide the scores?`
      }
      if (key === 'solutions_released' && release) {
        if (assignment.assessment_type === 'real time' && assignment.solutions_availability === 'automatic') {
          message = 'This is a "real time" assignment. By releasing the solutions, all students will be able to see them immediately, even if you chose "Automatic" as your option for Solutions Availability. Are you sure you want to show the solutions?'
        } else if (assignment.assessment_type === 'clicker') {
          message = 'Releasing the solutions at the assignment level also releases the solutions to any individual clicker questions you are not currently sharing. Release all solutions?'
        }
      }
      if (key === 'students_can_view_assignment_statistics' && release && !+assignment.show_scores) {
        message = 'Your students can\'t view their own scores yet. Are you sure that you want to show the assignment statistics?'
      }
      if (!message) {
        return true
      }
      return this.$bvModal.msgBoxConfirm(message, {
        title: `${release ? 'Release' : 'Hide'} ${{ shown: 'the assignment', show_scores: 'scores', solutions_released: 'solutions' }[key] || 'statistics'} for ${assignment.name}?`,
        size: 'md',
        buttonSize: 'sm',
        okTitle: release ? 'Release' : 'Hide',
        cancelTitle: 'Cancel',
        centered: true
      })
    },
    async toggleReleaseFromBadge (assignment, item) {
      if (this.releaseBadgeUpdating) {
        return
      }
      const release = !+assignment[item.key]
      if (!await this.getReleaseBadgeConfirmation(assignment, item.key, release)) {
        return
      }
      this.releaseBadgeUpdating = `${assignment.id}-${item.key}`
      try {
        const { data } = await axios.patch(this.getBulkSettingRequestUrl(assignment, item.key), { deactivate_auto_release: 0 })
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          await this.getAssignments()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.releaseBadgeUpdating = ''
    },
    getReleaseBadges (assignment) {
      // 2x2 block, read across: Assignment, Solutions / Scores, Statistics
      const badgeOrder = ['shown', 'solutions_released', 'show_scores', 'students_can_view_assignment_statistics']
      const items = this.getReleaseOverview(assignment)
      return badgeOrder
        .map(key => items.find(item => item.key === key))
        .filter(item => item && item.text !== 'N/A')
    },
    canSeeSettings (assignment) {
      // Every assignment has at least the Assignment (shown) release setting, for instructors and graders alike.
      return this.hasSettingsColumn
    },
    hasVisibilitySettings (assignment) {
      // Only instructors have visibility settings (the question URL view always applies, even to formative assignments).
      return this.user.role === 2
    },
    isSettingsExpanded (assignment) {
      return this.expandedAssignmentSettingIds.includes(assignment.id)
    },
    toggleAssignmentSettings (assignment) {
      this.expandedAssignmentSettingIds = this.isSettingsExpanded(assignment)
        ? this.expandedAssignmentSettingIds.filter(id => id !== assignment.id)
        : [...this.expandedAssignmentSettingIds, assignment.id]
    },
    toggleAllAssignmentSettings () {
      this.expandedAssignmentSettingIds = this.allAssignmentSettingsExpanded
        ? []
        : this.assignmentsWithSettings.map(assignment => assignment.id)
    },
    getReleaseSettingsTooltip () {
      return '<strong>Assignment</strong> controls whether students can see and open the assignment.<br><br>' +
        'Check <strong>Manual</strong> to release the item to your students now. ' +
        'Check <strong>Auto</strong> to release it automatically on its Release Date, which comes from the ' +
        'auto-release timing in the assignment\'s properties.<br><br>' +
        'Auto is not available for clicker assignments nor for assignments without an auto-release timing set.<br><br>' +
        'Scores are always released for auto-graded (real time) and learning tree assignments since students ' +
        'receive immediate feedback on each submission.'
    },
    showExternalAssignmentNoty () {
      this.$noty.info('This assignment has no questions to view because it is an external assignment.  To add questions, please edit the assignment and change the Source to ADAPT.')
      return false
    },
    addUntetheredAssignment () {
      this.$bvModal.hide('modal-confirm-add-untethered-assignment')
      this.addAssignmentIsImport
        ? this.initImportAssignment()
        : this.initAddAssignment(this.form, this.courseId, this.assignmentGroups, this.$noty, this.$moment, this.course.start_date, this.course.end_date, this.$bvModal, this.assignmentId)
    },
    confirmInitAddAssignment () {
      this.assignmentId = 0
      this.ownsAllQuestions = true
      this.hasSubmissionsOrFileSubmissions = false
      this.isBetaCourse
        ? this.$bvModal.show('modal-confirm-add-untethered-assignment')
        : this.initAddAssignment(this.form, this.courseId, this.assignmentGroups, this.$noty, this.$moment, this.course.start_date, this.course.end_date, this.$bvModal, this.assignmentId)
      const autoReleases = ['auto_release_shown',
        'auto_release_show_scores',
        'auto_release_show_scores_after',
        'auto_release_solutions_released',
        'auto_release_solutions_released_after',
        'auto_release_students_can_view_assignment_statistics',
        'auto_release_students_can_view_assignment_statistics_after']
      for (let i = 0; i < autoReleases.length; i++) {
        const autoRelease = autoReleases[i]
        this.form[autoRelease] = !autoRelease.includes('show_scores') ? this.course[autoRelease] : null
      }
    },
    confirmInitImportAssignment () {
      this.isBetaCourse
        ? this.$bvModal.show('modal-confirm-add-untethered-assignment')
        : this.initImportAssignment()
    },
    async getAssignmentGroupFilter (courseId) {
      try {
        const { data } = await axios.get(`/api/assignmentGroups/get-assignment-group-filter/${courseId}`)
        if (data.type === 'success') {
          this.chosenAssignmentGroup = data.assignment_group_filter
          console.log(this.assignmentGroupOptions)
        }
      } catch (error) {
        console.log(error)
      }
    },
    async handleSubmitAssignmentInfo () {
      this.savingAssignment = true
      this.$nextTick(() => {
        console.log(this.form)
      })

      this.prepareForm(this.form)
      console.log(this.form)
      try {
        this.form.course_id = this.courseId
        if (this.form.assessment_type === 'clicker') {
          const group = [{ value: { course_id: this.courseId }, text: 'Everybody' }]
          this.form.assign_tos[0].groups = group
          this.form.groups_0 = group
        }
        const { data } = !this.assignmentId
          ? await this.form.post(`/api/assignments`)
          : await this.form.patch(`/api/assignments/${this.assignmentId}`)
        let timeout = data.timeout ? data.timeout : 4000
        this.$noty[data.type](data.message, { timeout: timeout })
        this.savingAssignment = false
        if (data.type === 'success') {
          this.$bvModal.hide('modal-assignment-properties')
          await this.getAssignments()
        }
      } catch (error) {
        this.savingAssignment = false
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          fixInvalid()
          this.allFormErrors = this.form.errors.flatten()
          this.$bvModal.show('modal-form-errors-assignment-form')
        }
      }
    },
    viewAssignTos (assignTosToView) {
      this.assignTosToView = assignTosToView
      this.$bvModal.show('modal-assign-tos-to-view')
    },
    async saveNewOrder () {
      let orderedAssignments = []
      for (let i = 0; i < this.assignments.length; i++) {
        orderedAssignments.push(this.assignments[i].id)
      }

      let noChange = true
      for (let i = 0; i < this.currentOrderedAssignments.length; i++) {
        if (this.currentOrderedAssignments[i] !== this.assignments[i]) {
          noChange = false
        }
      }
      if (noChange) {
        return false
      }
      try {
        const { data } = await axios.patch(`/api/assignments/${this.courseId}/order`, { ordered_assignments: orderedAssignments })
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          for (let i = 0; i < this.assignments.length; i++) {
            this.assignments[i].order = i + 1
          }
          this.currentOrderedAssignments = this.assignments
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async checkAssignmentAutoRelease () {
      if (!this.importableAssignment) {
        this.$noty.info('Please choose an assignment.')
      } else {
        try {
          const { data } = await axios.get(`/api/auto-release/compare-to-default/assignment/${this.importableAssignment}/course/${this.courseId}`)
          if (data.type === 'error') {
            return false
          }
          this.nonMatchingAutoReleases = []
          if (data.non_matching_auto_releases.length) {
            this.nonMatchingAutoReleases = data.non_matching_auto_releases
            for (let i = 0; i < this.nonMatchingAutoReleases.length; i++) {
              const nonMatchingAutoRelease = this.nonMatchingAutoReleases[i]
              this.importedAssignmentAutoRelease[nonMatchingAutoRelease.key] = nonMatchingAutoRelease.course_default
            }
            console.log(this.nonMatchingAutoReleases)
            this.$nextTick(() => {
              this.$bvModal.show('modal-non-matching-releases')
            })
          } else {
            await this.handleImportAssignment()
          }
        } catch (error) {
          this.$noty.error(error.message)
        }
      }
    },
    async handleImportAssignment () {
      try {
        const importData = {
          level: this.importAssignmentForm.level,
          lms_grade_passback: this.importAssignmentForm.lms_grade_passback,
          reset_discuss_it_settings_to_default: this.importAssignmentForm.reset_discuss_it_settings_to_default,
          reset_clicker_settings_to_default: this.importAssignmentForm.reset_clicker_settings_to_default,
          remove_open_ended_questions_in_real_time_assignment: this.importAssignmentForm.remove_open_ended_questions_in_real_time_assignment
        }
        if (this.nonMatchingAutoReleases.length) {
          importData.auto_releases = this.importedAssignmentAutoRelease
        }
        const { data } = await axios.post(`/api/assignments/import/${this.importableAssignment}/to/${this.courseId}`,
          importData)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        this.getAssignments()
        this.$bvModal.hide('modal-non-matching-releases')
        this.$bvModal.hide('modal-import-assignment')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async initImportAssignment () {
      try {
        const { data } = await axios.get(`/api/assignments/importable-by-user/${this.courseId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.allAssignments = data.all_assignments
        this.$bvModal.show('modal-import-assignment')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async checkIfDiscussItOrClickerQuestionsExistsInAssignment (assignmentId) {
      this.discussItQuestionsExist = false
      try {
        const { data } = await axios.get(`/api/assignment-sync-question/discuss-it-clicker-or-open-ended-questions-by-course-or-assignment/assignment/${assignmentId}`)
        if (data.type === 'success') {
          this.importAssignmentForm.reset_discuss_it_settings_to_default = '0'
          this.discussItQuestionsExist = data.discuss_it_questions_exist
          this.importAssignmentForm.reset_clicker_settings_to_default = '0'
          this.clickerQuestionsExist = data.clicker_questions_exist
          this.openEndedQuestionsInRealTimeAssignmentExist = data.open_ended_questions_in_real_time_assignment_exist
          this.importAssignmentForm.remove_open_ended_questions_in_real_time_assignment = '0'
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async initCreateAssignmentFromTemplate (assignment) {
      const assignmentId = assignment.id
      // open_ended_questions_in_real_time_assignment_exists
      this.createAssignmentFromTemplateAssignmentId = assignmentId
      this.createAssignmentFromTemplateAssignmentName = assignment.name
      this.discussItQuestionsExist = false
      try {
        const { data } = await axios.get(`/api/assignment-sync-question/discuss-it-clicker-or-open-ended-questions-by-course-or-assignment/assignment/${assignmentId}`)
        if (data.type === 'success') {
          this.createAssignmentFromTemplateForm.reset_discuss_it_settings_to_default = '0'
          this.createAssignmentFromTemplateForm.link_discuss_it_questions = '1'
          this.discussItQuestionsExist = data.discuss_it_questions_exist
          this.createAssignmentFromTemplateForm.reset_clicker_settings_to_default = '0'
          this.clickerQuestionsExist = data.clicker_questions_exist
          this.openEndedQuestionsInRealTimeAssignmentExist = data.open_ended_questions_in_real_time_assignment_exist
          this.createAssignmentFromTemplateForm.remove_open_ended_questions_in_real_time_assignment = '0'
          this.$bvModal.show('modal-create-assignment-from-template')
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async handleCreateAssignmentFromTemplate () {
      try {
        const { data } = await this.createAssignmentFromTemplateForm.post(`/api/assignments/${this.createAssignmentFromTemplateAssignmentId}/create-assignment-from-template`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.getAssignments()
          this.$bvModal.hide('modal-create-assignment-from-template')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getGradeBook () {
      this.$router.push(`/courses/${this.courseId}/gradebook`)
    },
    getLockedQuestionsMessage (assignment) {
      if ((Number(assignment.has_submissions_or_file_submissions))) {
        return this.isLockedMessage()
      }
    },
    async getCourseInfo () {
      try {
        const { data } = await axios.get(`/api/courses/${this.courseId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.title = `${data.course.name} Assignments`
        this.course = data.course
        console.log(this.course)
        this.betaCoursesInfo = this.course.beta_courses_info
        this.isBetaCourse = this.course.is_beta_course
        this.lms = this.course.lms
        this.lmsError = this.course.lms_error
        const courseStartDate = this.$moment(this.course.start_date, 'YYYY-MM-DD')
        const november132023 = this.$moment('2023-11-13', 'YYYY-MM-DD')
        this.enableCanvasAPI = courseStartDate.isAfter(november132023)
        this.form.lms_api = this.course.lms_has_api_key && this.enableCanvasAPI
        this.lmsCourseOptions = [{ value: 0, text: 'Please choose a course' }]
        if (this.course.lms_courses.length) {
          for (let i = 0; i < this.course.lms_courses.length; i++) {
            let lmsCourse = this.course.lms_courses[i]
            this.lmsCourseOptions.push({ text: lmsCourse.name, value: lmsCourse.id })
          }
        }
        this.lmsCourseOptions.push({
          text: '-----------------------',
          value: 'divider-1',
          disabled: true
        })
        this.lmsCourseOptions.push(
          { value: -1, text: 'I don\'t see my course' })
        console.log(data)
      } catch (error) {
        this.$noty.error(error.message)
        return false
      }
      return true
    },
    getSubmissionFileView (assignmentId, submissionFiles) {
      if (submissionFiles === 0) {
        this.$noty.info('If you would like students to upload files as part of the assignment, please edit this assignment.')
        return false
      }
      this.$router.push(`/assignments/${assignmentId}/grading`)
    },
    async handleDeleteAssignment () {
      try {
        // the server checks again that these are still the comments that will be removed
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}`, {
          params: { confirmed_number_of_linked_discuss_it_comments: this.linkedDiscussItDeleteCheck.number_of_comments }
        })
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          // show what's true now (new comments, or comments that have since been scored)
          await this.getLinkedDiscussItDeleteCheck()
          return false
        }
        await this.resetAll('modal-delete-assignment')
        if (data.student_emails_associated_with_removed_comments && data.student_emails_associated_with_removed_comments.length) {
          this.removedDiscussItCommentEmails = data.student_emails_associated_with_removed_comments
          this.$bvModal.show('modal-removed-discuss-it-comment-emails')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getLinkedDiscussItDeleteCheck () {
      this.understandLinkedDiscussItCommentsRemoved = false
      const { data } = await axios.get(`/api/assignments/${this.assignmentId}/linked-discuss-it-delete-check`)
      if (data.type !== 'success') {
        throw new Error(data.message)
      }
      this.linkedDiscussItDeleteCheck = data
    },
    async deleteAssignment (assignment) {
      if (assignment.is_beta_assignment) {
        this.$bvModal.show('modal-cannot-delete-beta-assignment')
        return false
      }
      this.assignmentId = assignment.id
      this.tetheredBetaAssignmentExists = assignment.tethered_beta_assignment_exists && assignment.id !== 1389
      this.linkedDiscussItDeleteCheck = { can_delete: true, number_of_comments: 0, number_of_students: 0, assignment_names: [], cannot_delete_message: '' }
      if (!this.tetheredBetaAssignmentExists) {
        try {
          await this.getLinkedDiscussItDeleteCheck()
        } catch (error) {
          this.$noty.error(error.message)
          return false
        }
      }
      this.$bvModal.show('modal-delete-assignment')
    },
    async resetAll (modalId) {
      await this.getAssignments()
      // Hide the modal manually
      this.$nextTick(() => {
        this.$bvModal.hide(modalId)
      })
    },
    resetAssignmentGroupForm () {
      this.assignmentGroupForm.errors.clear()
      this.assignmentGroupForm.assignment_group = ''
    }
  }
}
</script>
<style scoped></style>
<style>
svg:focus, svg:active:focus {
  outline: none !important;
}

/*
  Small screens: the table keeps a minimum width and scrolls sideways instead of squeezing every column,
  with the arrow column and the assignment name pinned on the left.
*/
@media (max-width: 1439.98px) {
  /*
    Below this width, size columns to their content instead (the fixed layout squeezes the name column now that
    the badge and date columns have set widths), and let the table scroll sideways rather than shrink further.
  */
  .assignment-table {
    table-layout: auto !important;
    min-width: 1250px;
  }

  .assignment-table .sticky-col-name {
    min-width: 220px;
  }
}

@media (max-width: 767.98px) {
  .assignment-table {
    min-width: 900px;
  }

  .assignment-table .sticky-col-name {
    min-width: 170px;
  }
}

.assignment-table > tbody > tr {
  background-color: #fff;
}

.assignment-table .sticky-col {
  position: sticky;
  z-index: 1;
  background-color: inherit;
}

.assignment-table > thead .sticky-col {
  z-index: 2;
  background-color: #fff;
}

.assignment-table .sticky-col-first {
  left: 0;
}

.assignment-table .sticky-col-name {
  left: 64px;
}

/* the page title already draws a line right above the table, so drop the table's own top border */
.assignment-table > thead > tr > th {
  border-top: 0;
}

/* each assignment is its own <tbody> so its settings row moves with it when dragging */
.assignment-table > tbody + tbody {
  border-top-width: 1px;
}

.assignment-table .assignment-settings-toggle {
  line-height: 1;
}

.assignment-table .assignment-settings-row > td {
  background: #f8f9fa;
  border-top: 0;
}

/* Release to students and Visibility share this white card look */
.assignment-settings .release-settings-table {
  background: #fff;
}

.assignment-settings .release-settings-table th,
.assignment-settings .release-settings-table td {
  vertical-align: middle;
}

/*
  Visibility: Shown / Hidden side by side instead of stacked, each in a fixed-width slot so the second choice
  lines up from row to row (wide enough for "Assignment", the longest choice).
*/
.assignment-settings .visibility-settings-table .custom-radio {
  display: inline-block;
  min-width: 7.5rem;
  margin-right: 0.25rem;
}

.assignment-group-chip {
  font-size: 85%;
  font-weight: 500;
  padding: 0.35em 0.4em 0.35em 0.8em;
}

.assignment-group-chip-clear {
  background: transparent;
  border: 0;
  color: inherit;
  font-size: 1.1em;
  line-height: 1;
  margin-left: 0.25rem;
  padding: 0 0.25rem;
}

/* current group: pale highlight + checkmark instead of Bootstrap's solid blue, which clashed with the blue link text */
#assignment-group-filter .dropdown-item.active,
#assignment-group-filter .dropdown-item:active {
  background-color: #e8f0fe;
  color: #212529;
  font-weight: 600;
}

#assignment-group-filter .dropdown-item {
  color: #212529;
}

.assignment-group-filter-toggle {
  line-height: 1;
  vertical-align: baseline;
}

/* header buttons: Add / Import (assignments) and Gradebook / Properties (course), with a gap between the groups */
.header-button-group {
  display: inline-flex;
  flex-wrap: nowrap;
}

.header-button-group + .header-button-group {
  margin-left: 1.25rem;
}

/*
  Even space above and below the Released Status badges. Two-line dates were making each row taller than the
  2x2 badge block, which then sat at the top with a bigger gap underneath. The dates now use tighter line spacing
  (two lines are about as tall as the badge block), and every cell in the row is centered vertically, so if
  something else makes a row taller (a long wrapping name, say), the leftover space is split evenly.
*/
.assignment-table > tbody > tr.assignment-row > td,
.assignment-table > tbody > tr.assignment-row > th {
  vertical-align: middle;
}

.assignment-table .date-cell {
  line-height: 1.25;
}

/* Released Status: a 2x2 block read across (Assignment, Solutions / Scores, Statistics) */
.assignment-table .release-badges {
  display: grid;
  grid-template-columns: repeat(2, max-content);
  gap: 3px 4px;
  align-items: start;
}

.assignment-table .release-badge {
  font-weight: 500;
  text-align: left;
}

/*
  Group names normally wrap at spaces; a long name with no spaces breaks mid-word instead of spilling out of its
  150px cell over the Released Status badges. break-word (rather than anywhere) only breaks a word when it can't
  fit, so on narrower screens, where columns size to their content, the column still grows to fit the name.
*/
.assignment-table .assignment-group-cell {
  overflow-wrap: break-word;
}

/* name search above the table: wide enough for the placeholder, but it doesn't stretch across the page */
.assignment-name-filter {
  max-width: 320px;
}

/* grey bar separating the dates from the rest of the row */
.assignment-table .date-separator {
  border-left: 2px solid #ced4da;
}

/* on narrow screens Release Date is hidden, so the bar moves to Open Date */
@media (max-width: 767.98px) {
  .assignment-table .date-separator-narrow {
    border-left: 2px solid #ced4da;
  }
}
/* date headers: only the first word may wrap; "Date", the sort arrow and the (?) stay on one line */
.assignment-table .date-header-label {
  white-space: nowrap;
}

/* divider marking where the table scrolls sideways under the pinned columns (only shown when it does scroll) */
.assignment-table.scrolls-sideways .sticky-col-name {
  box-shadow: inset -1px 0 0 #dee2e6;
}

/*
  Date headers look like the other headers (same color as the header text) and turn blue on hover/focus to show
  they're clickable. They're spans, not links, so the site's link color never applies.
*/
.assignment-table .date-sort-toggle {
  white-space: normal;
  color: inherit;
  cursor: pointer;
}

.assignment-table .date-sort-toggle:hover,
.assignment-table .date-sort-toggle:focus {
  color: #007bff;
}

/* a span gets no focus ring of its own, so draw one for keyboard users */
.assignment-table .date-sort-toggle:focus-visible {
  outline: 2px solid #007bff;
  outline-offset: 2px;
  border-radius: 2px;
}

/* dragging is off while the list is sorted by a date */
.assignment-table .handle-disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.assignment-table .release-badge-toggle {
  border: 0;
  cursor: pointer;
}

.assignment-table .release-badge-toggle:hover,
.assignment-table .release-badge-toggle:focus {
  filter: brightness(0.9);
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.35);
  outline: none;
}

.assignment-table .release-badge-locked {
  border: 0;
  cursor: help;
}

.assignment-table .release-badge-spinner {
  width: 0.8em;
  height: 0.8em;
}

/*
  The Control Panel table scrolls inside this wrapper (both directions), so its two header rows -- the column
  names and the "Change all..." row -- can stay stuck to the top while you scroll down the list.
*/
.control-panel-scroll {
  max-height: calc(100vh - 200px);
  overflow: auto;
}

.settings-overview-table {
  margin-bottom: 0;
}

.settings-overview-table > thead {
  position: sticky;
  top: 0;
  z-index: 3;
}

.settings-overview-table > thead th {
  background: #fff;
  /* cell borders scroll away with the body, so draw the bottom line on the stuck header itself */
  box-shadow: inset 0 -1px 0 #dee2e6;
}

.settings-overview-table .bulk-settings-row td {
  background: #f1f4f8;
  box-shadow: inset 0 -2px 0 #dee2e6;
}

/* grey bar between the release columns and the visibility columns */
.settings-overview-table .cp-group-start {
  border-left: 2px solid #ced4da;
}

.settings-overview-table > thead > tr:first-child > th {
  border-top: 0;
}

.settings-overview-table .overview-release-column {
  min-width: 165px;
}

.settings-overview-table .bulk-settings-row select {
  min-width: 130px;
}

/* keep "Released on:" and its date together instead of wrapping into two columns */
.settings-overview-table .overview-release-date > div,
.settings-overview-table .overview-release-date > div > div {
  display: inline;
}

.settings-overview-dialog {
  max-width: 95vw;
}

.settings-overview-table th,
.settings-overview-table td {
  vertical-align: top;
}

.time-limit-tooltip .tooltip-inner {
  text-align: left;
  max-width: 240px;
}

.header-high-z-index table thead tr th {
  z-index: 5 !important;
  border-top: 1px !important; /*gets rid of the flickering issue at top when scrolling.*/
}
</style>
