<template>
  <div class="mt-2">
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-auto-release'" />
    <b-modal id="modal-confirm-global-update-auto-release-property"
             title="Confirm Global Update"
             no-close-on-backdrop
    >
      <p>
        You are about to turn all of your {{ globalAutoRelease.setting }} settings
        "{{ Boolean(globalAutoRelease.value) ? 'on' : 'off' }}" for:
      </p>
      <p class="text-center">
        <strong>{{
            globalAutoRelease.update_item === -1 ? 'the Entire Course' : globalAutoRelease.update_name
          }}</strong>.
      </p>
      <p v-if="globalAutoRelease.setting=== 'auto' && globalAutoRelease.value">
        This will only impact non-clicker assignments and auto-release properties with existing timings.
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-confirm-global-update-auto-release-property')"
        >
          Cancel
        </b-button>
        <b-button
          :variant="Boolean(globalAutoRelease.value) ? 'success' :'danger'"
          size="sm"
          class="float-right"
          @click="globalUpdateAutoReleaseProperty()"
        >
          {{ Boolean(globalAutoRelease.value) ? 'Turn On' : 'Turn Off' }}
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-apply-auto-release-to"
             title="Auto-release Options"
    >
      <b-form>
        <b-form-group
          label-cols-sm="3"
          label-cols-lg="2"
          label="Apply to "
          label-for="apply-to"
        >
          <b-form-radio-group
            id="apply-to"
            v-model="applyTo"
            class="mt-2"
            stacked
          >
            <b-form-radio value="all">
              All assignments <span v-b-tooltip.hover
                                    :title="`This option will override all of the auto-releases for all assignments currently in the course.  Future assignments will use these settings as the default.`"
            ><b-icon-question-circle />
              </span>
            </b-form-radio>
            <b-form-radio value="future">
              Future assignments <span v-b-tooltip.hover
                                       :title="`This option will not affect assignments that are currently in the course.  Future assignments will use these settings as the default.`"
            ><b-icon-question-circle />
              </span>
            </b-form-radio>
          </b-form-radio-group>
        </b-form-group>
      </b-form>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-apply-auto-release-to')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="saveCourseAutoRelease()"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <p v-show="courseId">
      Any of the course default options can be overridden at the assignment level within your assignment properties.
    </p>
    <b-card body-class="card-body-pl">
      <template #header>
        <div class="flex d-inline-flex align-items-center justify-content-between w-100">
          <div class="d-inline-flex">
            <div v-html="headerHtml" />
            <div class="ml-1">
              <a id="auto-release-tooltip"
                 href=""
                 style="color:black"
                 @click.prevent
              >
                <b-icon icon="question-circle" style="color:black; margin-bottom: 2px" />
              </a>
              <b-tooltip target="auto-release-tooltip"
                         delay="250"
                         triggers="hover focus"
              >
                With auto-release, you can automatically set a time to show your assignment, release scores,
                reveal solutions, and share class statistics with your students.
              </b-tooltip>
            </div>
          </div>
          <ConsultInsight :url="'https://commons.libretexts.org/insight/auto-release-settings'"/>
        </div>
      </template>
      <b-alert variant="info" show style="font-size:90%">
        Assignments will be shown the specified amount of time <strong>before</strong> your first "available on".
        Scores,
        solutions, and statistics will be released the specified amount of time <strong>after</strong> your last due
        date
        or final submissison date if applicable.
      </b-alert>
      <table class="table table-striped table-sm">
        <thead>
        <tr>
          <th scope="col">
            Item
          </th>
          <th scope="col">
            Time frame
          </th>
          <th scope="col" style="width:380px">
            Condition
          </th>
          <th v-show="false" scope="col">
            Manual<br>Override
            <QuestionCircleTooltip id="released-tooltip" />
            <b-tooltip target="released-tooltip"
                       delay="250"
                       triggers="hover focus"
            >
              You can override the released status for each of the items. If set to "no",
            </b-tooltip>
          </th>
        </tr>
        </thead>
        <tbody>
        <tr>
          <td>
            <AutoReleaseStatus :item="'Assignment'"
                               :released="autoReleaseOverrideForm.shown"
                               :assignment-id="assignmentId"
            />
          </td>
          <td>
            <b-row>
              <b-form-input
                id="auto_release_shown"
                v-model="autoReleaseForm.auto_release_shown"
                size="sm"
                type="text"
                placeholder="Ex. 2 days/3 hours"
                style="width:150px"
                :class="{ 'is-invalid': autoReleaseForm.errors.has('auto_release_shown') }"
                class="ml-2 mr-2"
                @keydown=" autoReleaseForm.errors.clear('auto_release_shown')"
              />
              <b-button size="sm" variant="outline-primary" @click="clearAutoRelease('shown')">
                Clear
              </b-button>
            </b-row>
            <ErrorMessage v-if="autoReleaseForm.errors.has('auto_release_shown')"
                          :message="autoReleaseForm.errors.get('auto_release_shown')"
            />
          </td>
          <td>
            before your {{ first() }} "available on"
          </td>
          <td v-show="false">
            <toggle-button
              class="mt-2"
              :width="60"
              :value="autoReleaseOverrideForm.shown"
              :sync="true"
              :font-size="14"
              :margin="4"
              :color="toggleColors"
              :labels="{checked: 'Yes', unchecked: 'No'}"
              @change="updateAutoUpdateAutoReleaseOverride('shown')"
            />
          </td>
        </tr>
        <tr>
          <td>
            <AutoReleaseStatus :item="'Scores'"
                               :released="autoReleaseOverrideForm.show_scores"
                               :assignment-id="assignmentId"
            />
          </td>
          <td>
            <b-row>
              <b-form-input
                id="auto_release_show_scores"
                v-model="autoReleaseForm.auto_release_show_scores"
                size="sm"
                type="text"
                placeholder=""
                style="width:150px"
                :disabled="['real time', 'learning tree'].includes(autoReleaseForm.assessment_type)"
                :class="{ 'is-invalid': autoReleaseForm.errors.has('auto_release_show_scores') }"
                class="ml-2 mr-2"
                @keydown="autoReleaseForm.errors.clear('auto_release_show_scores')"
              />
              <b-button size="sm" variant="outline-primary" @click="clearAutoRelease('show_scores')">
                Clear
              </b-button>
            </b-row>
            <ErrorMessage v-if="autoReleaseForm.errors.has('auto_release_show_scores')"
                          :message="autoReleaseForm.errors.get('auto_release_show_scores')"
            />
          </td>
          <td>
            <div v-if="['real time', 'learning tree'].includes(autoReleaseForm.assessment_type)">
              Automatically released with {{ autoReleaseForm.assessment_type }} assignments
            </div>
            <div v-if="!['real time', 'learning tree'].includes(autoReleaseForm.assessment_type)">
              <div v-if="acceptLate || courseId">
                <b-form-select v-model="autoReleaseForm.auto_release_show_scores_after"
                               :options="autoReleaseAfterOptions"
                               :class="{ 'is-invalid': autoReleaseForm.errors.has('auto_release_show_scores_after') }"
                               size="sm"
                               @change="autoReleaseForm.errors.clear('auto_release_show_scores_after')"
                />
                <has-error :form="autoReleaseForm" field="auto_release_show_scores_after" />
              </div>
              <div v-else>
                after your {{ last() }} "due date"
              </div>
            </div>
          </td>
          <td v-show="false">
            <toggle-button
              class="mt-2"
              :width="60"
              :value="autoReleaseOverrideForm.show_scores"
              :sync="true"
              :font-size="14"
              :margin="4"
              :color="toggleColors"
              :labels="{checked: 'Yes', unchecked: 'No'}"
              @change="updateAutoUpdateAutoReleaseOverride('show_scores')"
            />
          </td>
        </tr>
        <tr>
          <td>
            <AutoReleaseStatus :item="'Solutions'"
                               :released="autoReleaseOverrideForm.solutions_released"
                               :assignment-id="assignmentId"
            />
          </td>
          <td>
            <b-row>
              <b-form-input
                id="auto_release_solutions_released"
                v-model="autoReleaseForm.auto_release_solutions_released"
                size="sm"
                type="text"
                placeholder=""
                style="width:150px"
                :disabled="(autoReleaseForm.assessment_type === 'real time' && autoReleaseForm.solutions_availability === 'automatic')"
                :class="{ 'is-invalid': autoReleaseForm.errors.has('auto_release_solutions_released') }"
                class="ml-2 mr-2"
                @keydown=" autoReleaseForm.errors.clear('auto_release_solutions_released')"
              />
              <b-button size="sm" variant="outline-primary" @click="clearAutoRelease('solutions_released')">
                Clear
              </b-button>
            </b-row>
            <ErrorMessage v-if="autoReleaseForm.errors.has('auto_release_solutions_released')"
                          :message="autoReleaseForm.errors.get('auto_release_solutions_released')"
            />
          </td>
          <td>
            <div
              v-if="autoReleaseForm.solutions_availability === 'automatic'
                  && autoReleaseForm.assessment_type === 'real time'"
            >
              "Solutions Availability" is already set to automatic above in your Assignment Properties
            </div>
            <div v-else>
              <div v-if="acceptLate || courseId">
                <b-form-select v-model="autoReleaseForm.auto_release_solutions_released_after"
                               :options="autoReleaseAfterOptions"
                               :class="{ 'is-invalid': autoReleaseForm.errors.has('auto_release_solutions_released_after') }"
                               size="sm"
                               @change="autoReleaseForm.errors.clear('auto_release_solutions_released_after')"
                />
                <has-error :form="autoReleaseForm" field="auto_release_solutions_released_after" />
              </div>
              <div v-else>
                after your {{ last() }} "due date"
              </div>
            </div>
          </td>
          <td v-show="false">
            <toggle-button
              class="mt-2"
              :width="60"
              :value="autoReleaseOverrideForm.solutions_released"
              :sync="true"
              :font-size="14"
              :margin="4"
              :color="toggleColors"
              :labels="{checked: 'Yes', unchecked: 'No'}"
              @change="updateAutoUpdateAutoReleaseOverride('solutions_released')"
            />
          </td>
        </tr>
        <tr>
          <td>
            <AutoReleaseStatus :item="'Statistics'"
                               :released="autoReleaseOverrideForm.students_can_view_assignment_statistics"
                               :assignment-id="assignmentId"
            />
          </td>
          <td>
            <b-row>
              <b-form-input
                id="auto_release_show_statistics"
                v-model="autoReleaseForm.auto_release_students_can_view_assignment_statistics"
                size="sm"
                type="text"
                placeholder=""
                style="width:150px"
                :class="{ 'is-invalid': autoReleaseForm.errors.has('auto_release_students_can_view_assignment_statistics') }"
                class="ml-2 mr-2"
                @keydown=" autoReleaseForm.errors.clear('auto_release_students_can_view_assignment_statistics')"
              />
              <b-button size="sm" variant="outline-primary"
                        @click="clearAutoRelease('students_can_view_assignment_statistics')"
              >
                Clear
              </b-button>
            </b-row>
            <ErrorMessage v-if="autoReleaseForm.errors.has('auto_release_students_can_view_assignment_statistics')"
                          :message="autoReleaseForm.errors.get('auto_release_students_can_view_assignment_statistics')"
            />
          </td>
          <td>
            <div v-if="acceptLate || courseId">
              <b-form-select v-model="autoReleaseForm.auto_release_students_can_view_assignment_statistics_after"
                             :options="autoReleaseAfterOptions"
                             :class="{ 'is-invalid': autoReleaseForm.errors.has('auto_release_students_can_view_assignment_statistics_after') }"
                             size="sm"
                             @change="autoReleaseForm.errors.clear('auto_release_students_can_view_assignment_statistics_after')"
              />
              <has-error :form="autoReleaseForm" field="auto_release_students_can_view_assignment_statistics_after" />
            </div>
            <div v-else>
              after your {{ last() }} "due date"
            </div>
          </td>
          <td v-show="false">
            <toggle-button
              class="mt-2"
              :width="60"
              :value="autoReleaseOverrideForm.students_can_view_assignment_statistics"
              :sync="true"
              :font-size="14"
              :margin="4"
              :color="toggleColors"
              :labels="{checked: 'Yes', unchecked: 'No'}"
              @change="updateAutoUpdateAutoReleaseOverride('students_can_view_assignment_statistics')"
            />
          </td>
        </tr>
        </tbody>
      </table>
      <div v-if="courseId" class="float-right">
        <b-button variant="primary"
                  size="sm"
                  @click="initSaveCourseAutoRelease"
        >
          Save
        </b-button>
      </div>
    </b-card>
    <div v-if="courseId" class="pt-3">
      <b-card
        header="default"
        header-html="<h2 class=&quot;h7&quot;>Global Update</h2>"
      >
        <b-card-text>
          <b-container>
            <div class="mb-3">
              <b-row>
                <b-form-select v-model="globalAutoRelease.update_item"
                               style="width:400px"
                               size="sm"
                               :options="globalAutoReleaseUpdateOptions"
                               class="mt-1 ml-0"
                               @change="updateAutoReleaseFormupdate_name($event)"
                />
              </b-row>
              <div>
                <ErrorMessage v-if="globalAutoReleaseItemErrorMessage"
                              :message="globalAutoReleaseItemErrorMessage"
                />
              </div>
            </div>
            <b-row class="pb-2">
              <span class="pr-2"><label>Set all "manual" to :</label></span>
              <span class="pr-2">
                <b-button size="sm"
                          variant="success"
                          @click="initBulkUpdateAutoRelease('manual',1)"
                >
                  On</b-button>
              </span>
              <b-button size="sm"
                        variant="danger"
                        @click="initBulkUpdateAutoRelease('manual',0)"
              >
                Off
              </b-button>
            </b-row>
            <b-row>
              <span class="pr-2"><label>Set all "auto" to:</label></span>
              <span class="pr-2"><b-button
                size="sm"
                variant="success"
                @click="initBulkUpdateAutoRelease('auto',1)"
              >On</b-button></span>
              <b-button size="sm"
                        variant="danger"
                        @click="initBulkUpdateAutoRelease('auto',0)"
              >
                Off
              </b-button>
            </b-row>
          </b-container>
        </b-card-text>
      </b-card>
    </div>
  </div>
</template>

<script>

import AllFormErrors from './AllFormErrors.vue'
import ConsultInsight from './ConsultInsight.vue'
import Form from 'vform'
import { ToggleButton } from 'vue-js-toggle-button'
import axios from 'axios'
import AutoReleaseStatus from './AutoReleaseStatus.vue'
import ErrorMessage from './ErrorMessage.vue'
import { mapGetters } from 'vuex'

export default {
  name: 'Autorelease',
  components: { ErrorMessage, AutoReleaseStatus, AllFormErrors, ToggleButton, ConsultInsight },
  props: {
    autoReleaseForm: {
      type: Object,
      default: () => {
      }
    },
    courseId: {
      type: Number,
      default: 0
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    numAssignTos: {
      type: Number,
      default: 0
    },
    acceptLate: {
      type: Boolean,
      default: false
    },
    assessmentType: {
      type: String,
      default: ''
    },
    course: {
      type: Object,
      default: () => {
      }
    }
  },
  data: () => ({
      globalAutoReleaseItemErrorMessage: '',
      globalAutoReleaseUpdateOptions: [{ text: 'Apply global update to...', value: null }],
      globalAutoRelease: {
        update_item: null,
        update_name: ''
      },
      applyTo: 'all',
      autoReleaseAfterOptions: [],
      headerHtml: '',
      allFormErrors: [],
      toggleColors: window.config.toggleColors,
      autoReleaseOverrideForm: new Form({
        shown: false,
        show_scores: false,
        solutions_released: false,
        students_can_view_assignment_statistics: false,
        auto_release_show_scores_after: 'due date',
        auto_release_solutions_released_after: 'due date',
        auto_release_students_can_view_assignment_statistics_after: 'due date'
      })
    }
  ),
  computed: {
    isAdmin: () => window.config.isAdmin,
    ...mapGetters({
      user: 'auth/user'
    })
  },
  watch: {
    'autoReleaseForm.solutions_availability': {
      handler (solutionsAvailability) {
        if (solutionsAvailability === 'automatic' && this.autoReleaseForm.assessment_type === 'real time') {
          this.autoReleaseForm.auto_release_solutions_released = null
          this.autoReleaseForm.auto_release_solutions_released_after = null
        }
      },
      deep: true
    },
    'autoReleaseForm.assessment_type': {
      handler (assessmentType) {
        let autoReleases
        switch (assessmentType) {
          case ('learning tree'):
          case ('real time'):
            autoReleases = ['auto_release_show_scores', 'auto_release_show_scores_after']
            for (let i = 0; i < autoReleases.length; i++) {
              const autoRelease = autoReleases[i]
              if (this.autoReleaseForm[autoRelease]) {
                this.autoReleaseForm[autoRelease] = null
              }
            }
            break
          case ('delayed'):
            if (this.course) {
              autoReleases = ['auto_release_show_scores', 'auto_release_show_scores_after']
              for (let i = 0; i < autoReleases.length; i++) {
                const autoRelease = autoReleases[i]
                if (this.course[autoRelease]) {
                  this.autoReleaseForm[autoRelease] = this.course[autoRelease] ? this.course[autoRelease] : null
                }
              }
            }
            break
        }
      },
      deep: true
    },
    numAssignTos: function () {
      this.autoReleaseAfterOptions = [{
        value: null,
        text: 'Please choose an option'
      },
        {
          value: 'due date',
          text: `after your ${this.last()} "due date"`
        }, {
          value: 'final submission deadline',
          text: `after your ${this.last()} "final submission deadline"`
        }]
    }
  },
  mounted () {
    if (this.courseId) {
      this.getGlobalAutoReleaseUpdateOptions()
    }
    this.headerHtml = this.courseId ? '<h2 class="h7 m-0">Default Auto-Release</h2>' : '<h2 class="h7 m-0">Auto-Release</h2>'
    if (this.assignmentId) {
      this.getReleasedSettings()
    }
    const ifApplicable = this.courseId ? '(if applicable)' : ''
    this.autoReleaseAfterOptions = [{
      value: null,
      text: 'Please choose an option'
    },
      {
        value: 'due date',
        text: `after your ${this.last()} "due date"`
      }, {
        value: 'final submission deadline',
        text: `after your ${this.last()} "final submission deadline" ${ifApplicable}`
      }]
  },
  methods: {
    updateAutoReleaseFormupdate_name (value) {
      if (value) {
        this.globalAutoReleaseItemErrorMessage = ''
      }
      this.globalAutoRelease.update_name = this.globalAutoReleaseUpdateOptions.find(item => +item.value === +value).text
    },
    async getGlobalAutoReleaseUpdateOptions () {
      try {
        const { data } = await axios.get(`/api/auto-release/global-release-auto-update-options/course/${this.courseId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
        this.globalAutoReleaseUpdateOptions = [{ text: 'Apply global update to...', value: null }]
        for (let i = 0; i < data.global_auto_release_update_options.length; i++) {
          this.globalAutoReleaseUpdateOptions.push(data.global_auto_release_update_options[i])
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async globalUpdateAutoReleaseProperty () {
      console.error(this.globalAutoRelease)
      try {
        const { data } = await axios.patch(`/api/auto-release/global-update/course/${this.courseId}`, this.globalAutoRelease)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.$bvModal.hide('modal-confirm-global-update-auto-release-property')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    initBulkUpdateAutoRelease (setting, value) {
      if (!this.globalAutoRelease.update_item) {
        this.globalAutoReleaseItemErrorMessage = 'Please first choose something to globally update.'
        return false
      }
      this.globalAutoRelease.setting = setting
      this.globalAutoRelease.value = value
      this.$forceUpdate()

      this.$bvModal.show('modal-confirm-global-update-auto-release-property')
    },
    initSaveCourseAutoRelease () {
      this.$bvModal.show('modal-apply-auto-release-to')
    },
    clearAutoRelease (item) {
      this.autoReleaseForm[`auto_release_${item}`] = null
      if (this.acceptLate || this.courseId) {
        this.autoReleaseForm[`auto_release_${item}_after`] = null
      }
      this.autoReleaseForm.errors.clear(`auto_release_${item}`)
      this.autoReleaseForm.errors.clear(`auto_release_${item}_after`)
      this.$forceUpdate()
    },
    async getReleasedSettings () {
      try {
        const { data } = await axios.get(`/api/auto-release/statuses/${this.assignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        const autoReleaseStatuses = data.auto_release_statuses
        this.autoReleaseOverrideForm = new Form({
          shown: Boolean(autoReleaseStatuses.shown),
          show_scores: Boolean(autoReleaseStatuses.show_scores),
          solutions_released: Boolean(autoReleaseStatuses.solutions_released),
          students_can_view_assignment_statistics: Boolean(autoReleaseStatuses.students_can_view_assignment_statistics),
          auto_release_show_scores_after: 'final submission deadline',
          auto_release_solutions_released_after: 'final submission deadline',
          auto_release_students_can_view_assignment_statistics_after: 'final submission deadline'
        })
        console.log(this.autoReleaseOverrideForm)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateAutoUpdateAutoReleaseOverride (item) {
      let urlParam
      switch (item) {
        case ('shown'):
          urlParam = 'show-assignment'
          break
        case ('show_scores'):
          urlParam = 'show-scores'
          break
        case ('solutions_released'):
          urlParam = 'solutions-released'
          break
        case ('students_can_view_assignment_statistics'):
          urlParam = 'show-assignment-statistics'
          break
        default:
          this.$noty.error(`${item} is not a valid item to update on the release form.`)
          return
      }
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/${urlParam}/${+this.autoReleaseOverrideForm[item]}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.autoReleaseOverrideForm[item] = !this.autoReleaseOverrideForm[item]
          this.$emit('updateShowHideRelease', item)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    last () {
      return this.numAssignTos > 1 ? 'last' : ''
    },
    first () {
      return this.numAssignTos > 1 ? 'first' : ''
    },
    async saveCourseAutoRelease () {
      try {
        this.autoReleaseForm.apply_to = this.applyTo
        const { data } = await this.autoReleaseForm.patch(`/api/courses/auto-release/${this.courseId}`)
        this.$noty[data.type](data.message)
        this.$bvModal.hide('modal-apply-auto-release-to')
        if (data.type === 'error') {
          return false
        }
      } catch (error) {
        this.$bvModal.hide('modal-apply-auto-release-to')
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.autoReleaseForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-auto-release')
        }
      }
    }
  }
}
</script>
