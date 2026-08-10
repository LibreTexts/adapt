<template>
  <div>
    <b-alert :show="true" variant="info">
      Each LMS type can go down independently, so toggle a specific LMS type off/on across all schools using it.
      Turning off disables the LMS connection for every active course on that LMS type and emails instructions to
      each instructor. Turning back on re-enables those same courses and reminds instructors to reconcile grades.
    </b-alert>

    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <b-table v-show="!isLoading"
               striped
               hover
               :no-border-collapse="true"
               :items="lmsTypes"
               :fields="fields"
               class="border border-1 rounded"
      >
        <template v-slot:cell(currently_off_count)="data">
          {{ data.item.currently_off_count }} courses off due to outage
        </template>

        <template v-slot:cell(outage_status)="data">
          <toggle-button
            :key="`lms-outage-toggle-${data.item.lms_type_key}-${toggleRefreshKey}`"
            class="mt-1"
            :width="130"
            :value="data.item.currently_off_count === 0"
            :sync="true"
            :font-size="14"
            :margin="4"
            :color="toggleColors"
            :disabled="processingLmsTypeKey === data.item.lms_type_key || isTogglePinned(data.item)"
            :labels="{checked: 'No Outage', unchecked: 'Outage'}"
            @change="openConfirm(data.item)"
          />
        </template>
      </b-table>
    </div>

    <b-modal id="modal-confirm-lms-outage-action" :title="confirmModalTitle" @hidden="onModalHidden">
      <div v-if="lmsTypeToConfirm">
        <p v-if="confirmAction === 'turn-off'">
          This will disable the LMS connection for
          <strong>all {{ lmsTypeToConfirm.currently_linked_count }}</strong> active
          <strong>{{ lmsTypeToConfirm.lms_type }}</strong> courses across
          {{ lmsTypeToConfirm.school_count }} {{ pluralize(lmsTypeToConfirm.school_count, 'school') }}, and will email each instructor with instructions for
          their students.
        </p>
        <p v-else>
          This will re-enable the LMS connection for
          <strong>all {{ lmsTypeToConfirm.currently_off_count }}</strong>
          <strong>{{ lmsTypeToConfirm.lms_type }}</strong> courses currently marked as affected by the outage,
          and will email each instructor a reminder to reconcile grades.
        </p>
        <p class="font-weight-bold">Are you sure you want to continue?</p>
      </div>
      <template #modal-footer="{ cancel }">
        <b-button size="sm" @click="cancel()">
          Cancel
        </b-button>
        <b-button
          size="sm"
          :variant="confirmAction === 'turn-off' ? 'danger' : 'success'"
          :disabled="!!processingLmsTypeKey"
          @click="confirmAndSubmit"
        >
          {{ confirmAction === 'turn-off' ? `Yes, turn off ${lmsTypeToConfirm.lms_type}` : `Yes, turn ${lmsTypeToConfirm.lms_type} back on` }}
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { ToggleButton } from 'vue-js-toggle-button'
import axios from 'axios'

export default {
  components: {
    Loading,
    ToggleButton
  },
  data: () => ({
    isLoading: true,
    lmsTypes: [],
    lmsTypeToConfirm: null,
    confirmAction: null,
    processingLmsTypeKey: null,
    // Bumped after the confirm modal closes (confirmed or cancelled) to
    // force the toggle to re-render against the current (possibly
    // unchanged) server truth, since it flips visually on click before
    // confirmation happens.
    toggleRefreshKey: 0,
    toggleColors: window.config.toggleColors,
    fields: [
      { key: 'lms_type', label: 'LMS' },
      { key: 'currently_off_count', label: 'Off (Outage)' },
      { key: 'outage_status', label: 'Outage Status' }
    ]
  }),
  computed: {
    confirmModalTitle () {
      if (!this.lmsTypeToConfirm) {
        return ''
      }
      return this.confirmAction === 'turn-off'
        ? `Confirm: Turn Off ${this.lmsTypeToConfirm.lms_type}`
        : `Confirm: Turn ${this.lmsTypeToConfirm.lms_type} Back On`
    }
  },
  mounted () {
    this.getStatus()
  },
  methods: {
    // e.g. pluralize(1, 'school') -> 'school'; pluralize(8, 'school') -> 'schools'
    pluralize (count, singular, plural = null) {
      return count === 1 ? singular : (plural || `${singular}s`)
    },
    // While a confirmation is pending for a given row, don't let the
    // same toggle be clicked again.
    isTogglePinned (item) {
      return !!this.lmsTypeToConfirm && this.lmsTypeToConfirm.lms_type_key === item.lms_type_key
    },
    async getStatus () {
      try {
        const { data } = await axios.get('/api/lms-outage/status')
        this.isLoading = false
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.lmsTypes = data.lms_types
      } catch (error) {
        this.isLoading = false
        this.$noty.error(error.message)
      }
    },
    openConfirm (lmsType) {
      this.lmsTypeToConfirm = lmsType
      this.confirmAction = lmsType.currently_off_count > 0 ? 'turn-on' : 'turn-off'
      this.$bvModal.show('modal-confirm-lms-outage-action')
    },
    onModalHidden () {
      // Re-render the toggle against current data whether the admin
      // confirmed (data already refreshed below) or cancelled (data
      // unchanged, so the toggle snaps back to its prior state).
      this.toggleRefreshKey++
      this.lmsTypeToConfirm = null
      this.confirmAction = null
    },
    async confirmAndSubmit () {
      if (!this.lmsTypeToConfirm) {
        return
      }
      this.processingLmsTypeKey = this.lmsTypeToConfirm.lms_type_key
      try {
        const { data } = await axios.post(`/api/lms-outage/${this.lmsTypeToConfirm.lms_type_key}/${this.confirmAction}`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.$bvModal.hide('modal-confirm-lms-outage-action')
          await this.getStatus()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processingLmsTypeKey = null
    }
  }
}
</script>

<style scoped>

</style>
