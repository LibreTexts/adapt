<template>
  <div>
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-if="!isLoading">
        <b-card header-html="<h2 class=&quot;h7&quot;>Assignment Reminders</h2>">
          <b-card-text>
            <p>If you like, we can send you a reminder email notifying you when your next assignment is due.</p>
            <b-form-group
              id="hours_until_due"
              label-cols-sm="3"
              label-cols-lg="2"
              label="Please email me"
              label-for="Please email me"
            >
              <b-form-radio-group v-model="hoursUntilDue" stacked class="pt-2">
                <b-form-radio name="hours_until_due" value="0">
                  I don't need any reminder
                </b-form-radio>
                <b-form-radio name="hours_until_due" value="1">
                  1 hour before it's due
                </b-form-radio>
                <b-form-radio name="hours_until_due" value="6">
                  6 hours before it's due
                </b-form-radio>
                <b-form-radio name="hours_until_due" value="12">
                  12 hours before it's due
                </b-form-radio>
                <b-form-radio name="hours_until_due" value="24">
                  24 hours before it's due
                </b-form-radio>
                <b-form-radio name="hours_until_due" value="168">
                  1 week before it's due
                </b-form-radio>
              </b-form-radio-group>
            </b-form-group>
            <hr>
            <div class="float-right">
              <b-button variant="primary" @click="submitAssignmentNotificationForm">
                Update
              </b-button>
            </div>
          </b-card-text>
        </b-card>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

export default {
  scrollToTop: false,
  name: 'Notifications',
  components: {
    Loading
  },
  metaInfo () {
    return { title: 'Settings - Notifications' }
  },
  data: () => ({
    hoursUntilDue: 0,
    isLoading: true
  }),
  mounted () {
    this.getAssignmentNotification()
  },
  methods: {
    async getAssignmentNotification () {
      try {
        const { data } = await axios.get('/api/notifications/assignments')
        this.isLoading = false
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.hoursUntilDue = data.hours_until_due
      } catch (error) {
        this.$noty.error(error.message)
        this.isLoading = false
      }
    },
    async submitAssignmentNotificationForm () {
      try {
        const { data } = await axios.patch(`/api/notifications/assignments`, { 'hours_until_due': this.hoursUntilDue })
        this.$noty[data.type](data.message)
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    }
  }
}
</script>
