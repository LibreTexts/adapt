<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-assignment-form'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-link-course-to-lms-form'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-canvas-url-form'"/>

    <PageTitle v-if="canViewAssignments" :title="title"/>
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
        <ErrorMessage :message="errorMessage"/>
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
               title="Explanation of Submission Status"
               size="lg"
      >
        <table v-if="assignments.length" :key="`link-to-lms-${updateKey}`" class="table table-striped">
          <thead>
          <tr>
            <th scope="col">
              Submission Status
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
              Click the Main View toggle to show/hide the solutions or scores.
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
          The course has been successfully linked. <span v-if="assignments.length"
        >Assignments are now being linked.</span>
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
            <b-spinner small type="grow"/>
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
        <AssignTosToView ref="assignTosModal" :assign-tos-to-view="assignTosToView"/>
      </b-modal>

      <b-modal
        id="modal-create-assignment-from-template"
        ref="modal"
        size="lg"
        title="Create Assignment From Template"
      >
        <b-form-group
          id="create_assignment_from_template_level"
          label-cols-sm="3"
          label-cols-lg="2"
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
            <strong>Note:</strong> Questions will be linked to the source assignment's questions, not cloned as independent copies.
          </small>
        </b-alert>
        <b-form-group
          id="create_assignment_from_template_assign_to_groups"
          label-cols-sm="3"
          label-cols-lg="2"
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
          label-cols-lg="2"
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
          v-if="clickerQuestionsExist && createAssignmentFromTemplateForm.level === 'properties_and_questions'"
          id="reset_clicker_settings_to_default"
          label-cols-sm="3"
          label-cols-lg="2"
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
          label-cols-lg="2"
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
            Yes, copy assignment!
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
            Only formative assignments and assignments from formative courses are available to import into this formative course.
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
            <QuestionCircleTooltip :id="'lms_grade_passback_tooltip'"/>
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
        :title="tetheredBetaAssignmentExists ? 'Cannot Delete Assignment' :'Confirm Delete Assignment'"
        :hide-footer="tetheredBetaAssignmentExists"
      >
        <div v-show="!tetheredBetaAssignmentExists">
          <p>
            By deleting the assignment, you will also delete all student scores associated with the assignment.
          </p>
          <b-alert :show="hasLmsAssignmentId(assignmentId)" variant="danger">
            Please note that the assignment and all associated scores on your LMS will be deleted as
            well.
          </b-alert>
          <p><strong>Once an assignment is deleted, it can not be retrieved!</strong></p>
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
            @click="handleDeleteAssignment"
          >
            Yes, delete assignment!
          </b-button>
        </template>
      </b-modal>

      <b-container v-if="canViewAssignments">
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
              </p>
              <p v-if="course.is_brightspace">
                LTI URL:
                <span id="lti-url"> https://adapt.libretexts.org/api/lti/redirect-uri</span> <a href=""
                                                                                                aria-label="Copy LTI URL"
                                                                                                @click.prevent="doCopy('lti-url')"
              >
                <font-awesome-icon :icon="copyIcon"/>
              </a>
                <QuestionCircleTooltip :id="`lti-url-tooltip`"/>
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
                    This course is directly linked to the LMS course <span class="font-weight-bold"
                  >{{ course.lms_course_name }}</span>.
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
                        <b-spinner small type="grow"/>
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
                      <b-spinner small type="grow"/>
                      Processing...
                    </span>
                    <has-error :form="linkCourseToLMSForm" field="lms_course_id"/>
                  </div>
                </div>
              </div>
              <ConsultInsight v-if="user && user.role === 2 && course.lms"
                              :url="'https://commons.libretexts.org/insight/interfacing-adapt-to-lms-via-lti-and-api'"
              />
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
        <b-row class="mb-4" align-h="end">
          <b-col v-if="[2,4].includes(user.role) && !course.formative" lg="3">
            <b-form-select
              v-if="assignmentGroupOptions.length>1"
              v-model="chosenAssignmentGroup"
              title="Filter by assignment group"
              :options="assignmentGroupOptions"
              @change="updateAssignmentGroupFilter(courseId)"
            />
          </b-col>
          <b-col lg="9">
            <span class="float-right">
              <b-button v-if="user && [2,5].includes(user.role)"
                        class="ml-5 mr-1"
                        size="sm"
                        variant="primary"
                        @click="addAssignmentIsImport=false;confirmInitAddAssignment()"
              >
                New Assignment
              </b-button>
                <b-button v-if="(user && user.role === 2)"
                          class="mr-1"
                          size="sm"
                          variant="outline-primary"
                          @click="addAssignmentIsImport=true;confirmInitImportAssignment()"
                >
                  Import Assignment
                </b-button>
                <span v-if="!course.formative">
                <b-button
                  v-if="[2,4].includes(user.role)"
                  :class="(user && user.role === 4) ? 'float-right' : ''"
                  size="sm"
                  @click="getGradeBook()"
                >
                  Course Gradebook
                </b-button>
              </span>
              <b-button
                v-if="user && user.role === 2"
                :class="(user && user.role === 4) ? 'float-right' : ''"
                size="sm"
                variant="outline-primary"
                @click="$router.push(`/instructors/courses/${courseId}/properties`)"
              >
                Course Properties
              </b-button>
               <ConsultInsight v-if="user && user.role === 2"
                               :url="'https://commons.libretexts.org/insight/creating-a-new-adapt-assignment'"
               />
            </span>
          </b-col>
        </b-row>
      </b-container>
      <div v-show="hasAssignments" class="table-responsive">
        <toggle-button
          v-if="[2,4].includes(user.role) && !course.formative"
          tabindex="0"
          :width="125"
          :value="view === 'main view'"
          :sync="true"
          :font-size="14"
          :margin="4"
          :color="toggleColors"
          :labels="{checked: 'Main View', unchecked: 'Control Panel'}"
          @change="updateView"
        />
        <p v-show="atLeastOneAssignmentNotIncludedInWeightedAverage">
          Submissions for assignments marked with an asterisk (<span class="text-danger">*</span>) will not be included
          in when computing the final weighted average.
        </p>

        <table class="table table-striped table-responsive"
               aria-label="Assignment List"
               style="table-layout: fixed; width: 100%;"
        >
          <thead>
          <tr>
            <th scope="col" :style="getAssignmentNameWidth()">
              Assignment Name
            </th>
            <th v-if="view === 'control panel'" scope="col" style="width:110px">
              Scores
              <QuestionCircleTooltip :id="`auto-release-scores-tooltip`"/>
              <b-tooltip :target="`auto-release-scores-tooltip`"
                         delay="250"
                         triggers="hover focus"
              >
                <div v-html="getAutoReleaseTooltip('releasing your scores')"/>
              </b-tooltip>
            </th>
            <th v-if="view === 'control panel'" scope="col" style="width:85px">
              Scores Released On
            </th>
            <th v-if="view === 'control panel'" scope="col" style="width:120px">
              Solutions
              <QuestionCircleTooltip :id="`auto-release-solutions-tooltip`"/>
              <b-tooltip :target="`auto-release-solutions-tooltip`"
                         delay="250"
                         triggers="hover focus"
              >
                <div v-html="getAutoReleaseTooltip('showing the solutions')"/>
              </b-tooltip>
            </th>
            <th v-if="view === 'control panel'" scope="col" style="width:85px">
              Solutions Released On
            </th>
            <th v-if="view === 'control panel'" scope="col" style="width:120px">
              Statistics
              <QuestionCircleTooltip :id="`auto-release-statistics-tooltip`"/>
              <b-tooltip :target="`auto-release-statistics-tooltip`"
                         delay="250"
                         triggers="hover focus"
              >
                <div v-html="getAutoReleaseTooltip('showing the assignment statistics')"/>
              </b-tooltip>
            </th>
            <th v-if="view === 'control panel'" scope="col" style="width:85px">
              Statistics Released On
            </th>
            <th v-if="view === 'control panel'" scope="col" style="width:85px">
              Points Per Question
            </th>
            <th v-if="view === 'control panel' && user.role ===2" scope="col"
                style="width:100px"
            >
              Student<br>Names
              <QuestionCircleTooltip :id="'viewable-by-graders-tooltip'"/>
              <b-tooltip target="viewable-by-graders-tooltip"
                         delay="500"
                         triggers="hover focus"
              >
                You can optionally hide your students' names from your graders to avoid any sort of
                conscious or subconscious bias.
              </b-tooltip>
            </th>
            <th v-if="view === 'control panel' && user.role ===2" scope="col">
              Question URL View
              <QuestionCircleTooltip id="question-url-view-tooltip"/>
              <b-tooltip target="question-url-view-tooltip"
                         delay="500"
                         triggers="hover focus"
              >
                You can provide your students with a URL taking them directly to any question in the assignment, found
                within a given question's properties. From this question you can either show the entire assignment
                or just limit the view to that specific question.
              </b-tooltip>
            </th>
            <th v-if="view === 'main view' && [2,4].includes(user.role)" scope="col" style="width:115px">
              Released Status
              <QuestionCircleTooltip :id="`auto-release-shown-tooltip`"/>
              <b-tooltip :target="`auto-release-shown-tooltip`"
                         delay="250"
                         triggers="hover focus"
              >
                <div v-html="getAutoReleaseTooltip('showing the assignment')"/>
              </b-tooltip>
            </th>
            <th v-if="view === 'main view' && [2,4].includes(user.role)" scope="col">
              Group
            </th>
            <th v-if="view === 'main view' && [2,4].includes(user.role)" scope="col">
              Released On
            </th>
            <th v-if="view === 'main view' && [2,4].includes(user.role)" scope="col">
              Available On
            </th>
            <th v-if="view === 'main view' && [2,4].includes(user.role)" scope="col">
              Due
            </th>
            <th v-show="view === 'main view' && [2,4].includes(user.role)" scope="col" style="width:100px">
              Submission Status
              <span @mouseover="showAssignmentStatusModal()" @mouseout="mouseOverAssignmentStatus = false">
                  <QuestionCircleTooltip/>
                </span>
            </th>
            <th v-if="view === 'main view'" scope="col" :style="lms ? 'width: 175px' :'width: 125px'">
              Actions
            </th>
          </tr>
          </thead>
          <tbody is="draggable" :key="assignments.length"
                 v-model="assignments"
                 tag="tbody"
                 :options="{handle: '.handle'}"
                 @end="saveNewOrder"
          >
          <tr
            v-for="assignment in assignments"
            v-show="chosenAssignmentGroup === null || assignment.assignment_group === chosenAssignmentGroupText"
            :key="assignment.id"
            :style="getAssignmentRowStyle(assignment)"
          >
            <th scope="row">
              <b-icon icon="list" class="handle"/>
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
              <router-link v-if="assignment.source !== 'x'"
                           :to="{ name: 'instructors.assignments.questions',params:{assignmentId:assignment.id}}"
              >
                {{ assignment.name }}
              </router-link>
              <a v-if="assignment.source === 'x'" href="" @click.prevent="showExternalAssignmentNoty()"
              >{{ assignment.name }}</a> <span v-show="!assignment.include_in_weighted_average"
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
                    <div v-html="getLockedQuestionsMessage(assignment)"/>
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
                  ><b-icon-question-circle/></a>
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
                  ><b-icon-question-circle/></a>
                </span>
            </th>
            <td v-if="view === 'control panel'">
              <div v-if="isFormative (assignment)">
                N/A
              </div>
              <div v-if="!isFormative (assignment)">
                <ShowHideAssignmentProperties
                  :key="`show-scores-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                  :assignment="assignment"
                  :property="'show_scores'"
                  @refreshPage="getAssignments"
                />
              </div>
            </td>
            <td v-if="view === 'control panel'" class="show_scores_date">
              <AutoReleaseDate :key="`auto-release-date-${assignment.id}`"
                               :assignment="assignment"
                               :property="'show_scores_date'"
                               :auto-release-activated="assignment.auto_release_activated_show_scores"
              />
            </td>
            <td v-if="view === 'control panel'">
              <div v-if="isFormative (assignment)">
                N/A
              </div>
              <div v-if="!isFormative (assignment)">
                <ShowHideAssignmentProperties
                  :key="`show-solutions-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                  :assignment="assignment"
                  :property="'solutions_released'"
                  @refreshPage="getAssignments"
                />
              </div>
            </td>
            <td v-if="view === 'control panel'" class="solutions_released_date">
              <AutoReleaseDate :key="`auto-release-date-${assignment.id}`"
                               :assignment="assignment"
                               :property="'solutions_released_date'"
                               :auto-release-activated="assignment.auto_release_activated_solutions_released"
              />
            </td>
            <td v-if="view === 'control panel'">
              <div v-if="isFormative (assignment) || assignment.assessment_type === 'clicker'">
                N/A
              </div>
              <div v-if="!isFormative (assignment) && assignment.assessment_type !== 'clicker'">
                <ShowHideAssignmentProperties
                  :key="`students-can-view-assignment-statistics-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                  :assignment="assignment"
                  :property="'students_can_view_assignment_statistics'"
                  @refreshPage="getAssignments"
                />
              </div>
            </td>
            <td v-if="view === 'control panel'"
                class="students_can_view_assignment_statistics_date"
            >
              <AutoReleaseDate :key="`auto-release-date-${assignment.id}`"
                               :assignment="assignment"
                               :property="'students_can_view_assignment_statistics_date'"
                               :auto-release-activated="assignment.auto_release_activated_students_can_view_assignment_statistics"
              />
            </td>
            <td v-if="view === 'control panel'">
              <div v-if="isFormative (assignment)">
                N/A
              </div>
              <div v-if="!isFormative (assignment)">
                <ShowPointsPerQuestionToggle
                  :key="`students-can-view-assignment-statistics-toggle-${assignment.id}`"
                  :assignment="assignment"
                  :radio-buttons="true"
                />
              </div>
            </td>
            <td v-if="view === 'control panel' && user.role === 2">
              <div v-if="isFormative (assignment)">
                N/A
              </div>
              <div v-if="!isFormative (assignment)">
                <GradersCanSeeStudentNamesToggle
                  :key="`students-can-view-assignment-statistics-toggle-${assignment.id}`"
                  :assignment="assignment"
                  :radio-buttons="true"
                />
              </div>
            </td>
            <td v-if="view === 'control panel' && user.role === 2">
              <QuestionUrlViewToggle :key="`question-url-view-toggle-${assignment.id}`"
                                     :assignment="assignment"
                                     :radio-buttons="true"
              />
            </td>
            <td v-if="view === 'main view' && [2,4].includes(user.role)">
              <ShowHideAssignmentProperties
                :key="`shown-toggle-${assignment.id}-${showHideAssignmentPropertiesKey}`"
                :assignment="assignment"
                :property="'shown'"
                @refreshPage="getAssignments"
              />
            </td>
            <td v-if="view === 'main view' && [2,4].includes(user.role)">
              <div v-if="isFormative (assignment)">
                N/A
              </div>
              <div v-if="!isFormative (assignment)">
                {{ assignment.assignment_group }}
              </div>
            </td>
            <td v-if="view === 'main view' && [2,4].includes(user.role)" class="show_date">
              <AutoReleaseDate :key="`auto-release-date-${assignment.id}`"
                               :assignment="assignment"
                               :property="'show_date'"
                               :auto-release-activated="assignment.auto_release_activated_shown"
              />
            </td>
            <td v-if="view === 'main view' && [2,4].includes(user.role)">
              <div v-if="!showAssignTos(assignment)">
                N/A
              </div>
              <div v-if="showAssignTos(assignment)">
                  <span v-if="assignment.assign_tos.length === 1">
                    {{ $moment(assignment.assign_tos[0].available_from, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY') }}<br>
                    {{ $moment(assignment.assign_tos[0].available_from, 'YYYY-MM-DD HH:mm:ss A').format('h:mmA') }}
                  </span>
                <span v-if="assignment.assign_tos.length > 1">
                    <b-button variant="primary" size="sm" @click="viewAssignTos(assignment.assign_tos)">View</b-button>
                  </span>
              </div>
            </td>
            <td v-if="view === 'main view' && [2,4].includes(user.role)" style="width:200px">
              <div v-if="!showAssignTos(assignment)">
                N/A
              </div>
              <div v-if="showAssignTos(assignment)">
                  <span v-if="assignment.assign_tos.length === 1">
                    <span v-if="!showFinalSubmissionDeadline(assignment.assign_tos[0])">
                      {{ $moment(assignment.assign_tos[0].due, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY') }}<br>
                      {{ $moment(assignment.assign_tos[0].due, 'YYYY-MM-DD HH:mm:ss A').format('h:mmA') }}
                    </span>
                    <span v-if="showFinalSubmissionDeadline(assignment.assign_tos[0])">
                      {{
                        $moment(assignment.assign_tos[0].final_submission_deadline_date, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY')
                      }}<br>
                      {{
                        $moment(assignment.assign_tos[0].final_submission_deadline_time, 'HH:mm:ss A').format('h:mmA')
                      }}
                    </span>
                  </span>
              </div>
            </td>
            <td v-if="view === 'main view' && [2,4].includes(user.role)">
              <div v-if="!showAssignTos(assignment)">
                N/A
              </div>
              <div v-if="showAssignTos(assignment)">
                  <span v-if="assignment.assign_tos.length === 1"
                        :class="getStatusTextClass(assignment.assign_tos[0].status)"
                  >{{ assignment.assign_tos[0].status }}</span>
                <span v-if="assignment.assign_tos.length > 1" v-html="assignment.overall_status"/>
              </div>
            </td>
            <td v-if="view === 'main view'">
              <div class="mb-0">
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
                      Create Assignment From Template
                    </b-tooltip>
                    <a :id="getTooltipTarget('createAssignmentFromTemplate',assignment.id)"
                       href="#"
                       class="pr-1"
                       :aria-label="`Create assignment from ${assignment.name} template`"
                       @click="initCreateAssignmentFromTemplate(assignment.id)"
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
                      <b-icon icon="trash" class="assignment-icon" :aria-label="`Delete ${assignment.name}`"/>
                    </a>
                  </span>
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
import { ToggleButton } from 'vue-js-toggle-button'
import { getTooltipTarget, initTooltips } from '~/helpers/Tooptips'
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
import draggable from 'vuedraggable'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { faLink } from '@fortawesome/free-solid-svg-icons'
import AllFormErrors from '~/components/AllFormErrors'
import ShowPointsPerQuestionToggle from '~/components/ShowPointsPerQuestionToggle'
import GradersCanSeeStudentNamesToggle from '~/components/GradersCanSeeStudentNamesToggle'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import QuestionUrlViewToggle from '~/components/QuestionUrlViewToggle.vue'
import LMSGradePassback from '~/components/LMSGradePassback.vue'
import GrantLmsApiAccess from '~/components/GrantLmsApiAccess.vue'
import { isMobile } from '~/helpers/mobileCheck'
import ShowHideAssignmentProperties from '~/components/ShowHideAssignmentProperties.vue'
import showHideAssignmentProperties from '../../components/ShowHideAssignmentProperties.vue'
import AutoReleaseDate from '../../components/AutoReleaseDate.vue'
import ErrorMessage from '../../components/ErrorMessage.vue'
import ConsultInsight from '~/components/ConsultInsight.vue'
import { doCopy } from '../../helpers/Copy'
import consultInsight from '../../components/ConsultInsight.vue'

export default {
  middleware: 'auth',
  components: {
    ConsultInsight,
    ErrorMessage,
    AutoReleaseDate,
    ShowHideAssignmentProperties,
    GrantLmsApiAccess,
    LMSGradePassback,
    QuestionUrlViewToggle,
    ToggleButton,
    Loading,
    AssignmentProperties,
    AssignTosToView,
    draggable,
    FontAwesomeIcon,
    AllFormErrors,
    ShowPointsPerQuestionToggle,
    GradersCanSeeStudentNamesToggle
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
    atLeastOneAssignmentNotIncludedInWeightedAverage: false,
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
    view: 'main view',
    hasSubmissionsOrFileSubmissions: false,
    toggleColors: window.config.toggleColors,
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
    assignmentGroupOptions: [],
    createAssignmentFromTemplateForm: new Form({
      level: 'properties_and_questions',
      assign_to_groups: 1
    }),
    createAssignmentFromTemplateAssignmentId: 0,
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
    showPointsPerQuestionTooltip: {
      fallbackPlacement: ['right'],
      placement: 'right',
      title: 'In case you only grade a random subset of questions, you can hide the number of points per question so that your students won\'t know which questions you\'ll be grading.'
    },
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
  },
  async mounted () {
    window.addEventListener('keydown', this.quickSave)
    this.initAddAssignment = initAddAssignment
    this.editAssignmentProperties = editAssignmentProperties
    this.prepareForm = prepareForm
    this.getTooltipTarget = getTooltipTarget
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
      for (let i = 0; i < this.assignments.length; i++) {
        if (!this.assignments[i].include_in_weighted_average) {
          this.atLeastOneAssignmentNotIncludedInWeightedAverage = true
        }
      }
      this.initAssignmentGroupOptions(this.assignments)
      if (this.user.role === 2) {
        this.updateAssignmentGroupFilter(this.courseId)
      }
    }
  },
  methods: {
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
    getAssignmentNameWidth () {
      if (this.user.role === 5) {
        return 'width:90%'
      } else {
        return this.view === 'main view' ? 'width:400px' : 'width:185px'
      }
    },
    getAutoReleaseTooltip (setting) {
      return `If you have set up auto-release for ${setting}, then you can optionally activate or de-activate the auto-release below.<br><br>This option is not available for clicker assignments nor for assignments without the auto-release set.`
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
    updateView () {
      this.view = this.view === 'main view'
        ? 'control panel'
        : 'main view'
      if (this.view === 'main view') {
        this.getAssignments()
      }
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
    async initCreateAssignmentFromTemplate (assignmentId) {
      //open_ended_questions_in_real_time_assignment_exists
      this.createAssignmentFromTemplateAssignmentId = assignmentId
      this.discussItQuestionsExist = false
      try {
        const { data } = await axios.get(`/api/assignment-sync-question/discuss-it-clicker-or-open-ended-questions-by-course-or-assignment/assignment/${assignmentId}`)
        if (data.type === 'success') {
          this.createAssignmentFromTemplateForm.reset_discuss_it_settings_to_default = '0'
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
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}`)
        this.$noty[data.type](data.message)
        await this.resetAll('modal-delete-assignment')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    deleteAssignment (assignment) {
      if (assignment.is_beta_assignment) {
        this.$bvModal.show('modal-cannot-delete-beta-assignment')
        return false
      }
      this.assignmentId = assignment.id
      this.tetheredBetaAssignmentExists = assignment.tethered_beta_assignment_exists && assignment.id !== 1389
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

.header-high-z-index table thead tr th {
  z-index: 5 !important;
  border-top: 1px !important; /*gets rid of the flickering issue at top when scrolling.*/
}
</style>
