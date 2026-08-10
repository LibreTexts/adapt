<template>
  <div>
    <div v-if="hasAccess">
      <PageTitle title="LTI Integrations"/>
      <div class="vld-parent">
        <loading :active.sync="isLoading"
                 :can-cancel="true"
                 :is-full-page="true"
                 :width="128"
                 :height="128"
                 color="#007BFF"
                 background="#FFFFFF"
        />
        <b-tabs content-class="mt-3">
          <b-tab title="Registrations" active>
            <b-form-group
              label-cols-sm="2"
              label-cols-lg="1"
              label="Campus*"
              label-size="sm"
            >
              <b-form-input
                id="campus"
                v-model="ltiPendingRegistrationForm.campus"
                type="text"
                size="sm"
                required
                :class="{ 'is-invalid': ltiPendingRegistrationForm.errors.has('campus') }"
                @keydown="ltiPendingRegistrationForm.errors.clear('campus')"
              />
              <has-error :form="ltiPendingRegistrationForm" field="campus"/>
              <b-button variant="primary" size="sm" class="mt-2" @click="saveLTIPendingRegistration">
                Submit
              </b-button>
            </b-form-group>
            <div v-if="campusId">
              <div class="mb-2">
                Canvas: <span id="canvas-url">{{ origin }}/lti/canvas/config/{{ campusId }}</span>

                <span style="cursor: pointer;" @click="doCopy('canvas-url')"><font-awesome-icon :icon="copyIcon"/></span>
              </div>
              <div class="mb-2" v-show="false">
                Moodle: <span id="moodle-url">{{ origin }}/lti/moodle/config/{{ campusId }}</span>
                <span style="cursor: pointer;" @click="doCopy('moodle-url')"><font-awesome-icon :icon="copyIcon"/></span>
              </div>
              <div class="mb-2">
                Blackboard: <span id="blackboard-url">{{ origin }}/lti/blackboard/config/{{
                  campusId
                }}</span>
                <span style="cursor: pointer;" @click="doCopy('blackboard-url')"><font-awesome-icon :icon="copyIcon"
                /></span>
              </div>
              <div class="mb-2">
                Brightspace: <span id="brightspace-url">{{ origin }}/lti/brightspace/config/{{
                  campusId
                }}</span>
                <span style="cursor: pointer;" @click="doCopy('brightspace-url')"><font-awesome-icon :icon="copyIcon"
                /></span>
              </div>
            </div>
            <b-table v-show="ltiRegistrations.length"
                     striped
                     hover
                     :fields="fields"
                     :items="ltiRegistrations"
                     class="border border-1 rounded"
            >
              <template v-slot:cell(active)="data">
                <toggle-button
                  class="mt-1"
                  :width="57"
                  :value=" parseInt(data.item.active) === 1"
                  :sync="true"
                  :font-size="14"
                  :margin="4"
                  :color="toggleColors"
                  :labels="{checked: 'Yes', unchecked: 'No'}"
                  @change="toggleActive(data.item.id)"
                />
              </template>
              <template v-slot:cell(api)="data">
    <span v-if="data.item.api === 'No'">
      No
      <span :id="`api-config-url-${data.item.campus_id}`" style="display:none">https://adapt.libretexts.org/canvas/config/api/{{
          data.item.campus_id
        }}</span>
      <span style="cursor: pointer;" class="text-primary" @click="doCopy(`api-config-url-${data.item.campus_id}`)">
        <font-awesome-icon :icon="copyIcon"/>
      </span>
    </span>
                <span v-else>{{ data.item.api }}</span>
              </template>
            </b-table>
          </b-tab>
          <b-tab title="Outages">
            <LmsOutageManager/>
          </b-tab>
        </b-tabs>
      </div>
    </div>
  </div>
</template>

<script>
import Form from 'vform'
import { mapGetters } from 'vuex'
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { ToggleButton } from 'vue-js-toggle-button'
import { doCopy } from '~/helpers/Copy'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import LmsOutageManager from '~/components/LmsOutageManager'

export default {
  components: {
    Loading,
    ToggleButton,
    FontAwesomeIcon,
    LmsOutageManager
  },
  data: () => ({
    copyIcon: faCopy,
    origin: window.location.origin,
    campusId: '',
    ltiPendingRegistrationForm: new Form({
      campus: ''
    }),
    ltiRegistrationForm: new Form({
      admin_name: '',
      admin_email: '',
      url: '',
      developer_key_id: ''
    }),
    toggleColors: window.config.toggleColors,
    hasAccess: false,
    isLoading: true,
    ltiRegistrations: [],
    fields: [{
      key: 'auth_server',
      label: 'URL'
    },
      'campus_id',
      'name',
      {
        key: 'api',
        label: 'API',
        thStyle: {
          width: '80px'
        }
      },
      'active'
    ]
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    isAdmin: () => window.config.isAdmin
  },
  mounted () {
    this.hasAccess = this.isAdmin && (this.user !== null)
    if (!this.hasAccess) {
      this.$router.push({ name: 'no.access' })
      return false
    }
    this.getLTIRegistrations()
  },
  methods: {
    doCopy,
    async saveLTIPendingRegistration () {
      try {
        const { data } = await this.ltiPendingRegistrationForm.post(`/api/lti-pending-registration`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.campusId = data.campus_id
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    async toggleActive (registrationId) {
      try {
        const { data } = await axios.patch(`/api/lti-registration/active/${registrationId}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          await this.getLTIRegistrations()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getLTIRegistrations () {
      try {
        const { data } = await axios.get(`/api/lti-registration`)
        this.isLoading = false
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.ltiRegistrations = data.lti_registrations
        const blackboard = data.blackboard
        for (let i = 0; i < blackboard.length; i++) {
          const value = {
            auth_server: 'https://blackboard.com',
            campus_id: 'N/A',
            name: blackboard[i].name,
            api: 'no',
            active: 1
          }
          this.ltiRegistrations.push(value)
        }
      } catch
        (error) {
        this.$noty.error(error.message)
      }
      this.isLoading = false
    }
  }
}
</script>

<style scoped>

</style>
