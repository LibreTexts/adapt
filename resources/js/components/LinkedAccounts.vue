<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-link-accounts'" />
    <b-modal id="modal-no-longer-linked"
             title="No longer linked"
             @hidden="reloadPage"
    >
      Your accounts are no longer linked.
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="reloadPage"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-unlink-account"
             title="Unlink Account"
    >
      <p>Please confirm that you would like to unlink your account from:</p>
      <p class="text-center font-weight-bold">
        {{ accountToUnlink.email }}
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-confirm-unlink-account')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="danger"
          class="float-right"
          @click="unlinkAccount()"
        >
          Unlink
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-your-accounts-have-been-linked"
             title="Success!"
             size="lg"
             no-close-on-backdrop
             no-close-on-esc
             @hidden="reloadPage"
    >
      Your accounts have been linked. You will be able to switch back and forth between your accounts by using the
      dropdown in
      the upper right-hand corner of the page.
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-your-accounts-have-been-linked')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-validate-code"
             title="Validate Code"
    >
      <p>
        Please enter the validation code sent to your other ADAPT email account. If you didn't receive a code, we can
        always
        <a href="" @click.prevent="emailLinkToAccountCode(true)">resend it</a>.
      </p>
      <b-form-group
        label-cols-sm="5"
        label-cols-lg="4"
        label="Validation Code*"
        label-for="validation_code"
      >
        <b-form-input
          id="validation_code"
          v-model="validateCodeForm.validation_code"
          required
          type="text"
          :class="{ 'is-invalid': validateCodeForm.errors.has('validation_code') }"
          @keydown="validateCodeForm.errors.clear('validation_code')"
        />
        <has-error :form="validateCodeForm" field="validation_code" />
      </b-form-group>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-form-link-accounts')"
        >
          Cancel
        </b-button>

        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="validateCode()"
        >
          Validate
        </b-button>
      </template>
    </b-modal>
    <div class="mt-3">
      <div v-if="isMainAccount() || !linkedAccounts.length">
        <b-card>
          <template #header>
            <div class="d-flex align-items-center justify-content-between">
              <h2 class="h7 m-0">Link Accounts</h2>
              <ConsultInsight :url="'https://commons.libretexts.org/insight/how-to-handle-multiple-instructor-accounts-in-adapt'"/>
            </div>
          </template>
          <p>
            You can link multiple ADAPT instructor accounts together so that you can easily switch between your
            accounts.
            This account will
            serve as the main account
            so that additional linked accounts should be added here.
          </p>
          <b-form-group
            label-cols-sm="2"
            label-cols-lg="1"
            label="Email*"
            label-for="email"
          >
            <b-form-input
              id="account_to_link_to"
              v-model="accountToLinkToForm.email"
              required
              placeholder="Email address of your other ADAPT instructor account"
              type="text"
              :class="{ 'is-invalid': accountToLinkToForm.errors.has('email') }"
              @keydown="accountToLinkToForm.errors.clear('email')"
            />
            <has-error :form="accountToLinkToForm" field="email" />
          </b-form-group>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="emailLinkToAccountCode()"
          >
            Send Code
          </b-button>
        </b-card>
        <div class="mt-3">
          <b-card header-html="<h2 class=&quot;h7&quot;>Unlink Account</h2>">
            <b-alert variant="info" :show="!linkedAccounts.length">
              You currently have no linked accounts.
            </b-alert>
            <div v-if="linkedAccounts.length" class="mb-3">
              Unlinking an account will only remove the connection between them. No other information will
              be
              removed
              from our system.
            </div>
            <div v-for="linkedAccount in linkedAccounts"
                 :key="`linked-account-${linkedAccount.id}`"
                 class="mb-2"
            >
              <span v-if="linkedAccount.id !== user.id">{{ linkedAccount.email }}
                <b-button variant="danger"
                          size="sm"
                          @click="initUnlinkAccount(linkedAccount)"
                >
                  Unlink Account
                </b-button>
              </span>
            </div>
          </b-card>
        </div>
      </div>
      <div v-if="!isMainAccount() && linkedAccounts.length">
        <b-alert show variant="info">
          Your are currently signed into a linked account. If you would like to adjust which accounts are linked, please switch
          to the main account:
          <strong>{{ linkedAccounts.find(item => item.main_account).email }}</strong>.
        </b-alert>
      </div>
    </div>
  </div>
</template>

<script>
import AllFormErrors from '~/components/AllFormErrors'
import ConsultInsight from '~/components/ConsultInsight'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import { mapGetters } from 'vuex'
import axios from 'axios'
import Form from 'vform'

export default {
  scrollToTop: false,
  components: { AllFormErrors, ConsultInsight },
  metaInfo () {
    return { title: 'Settings - Linked Accounts' }
  },
  data: () => ({
    accountToUnlink: {},
    linkedAccounts: [],
    allFormErrors: [],
    accountToLinkToForm: new Form({
      email: ''
    }),
    validateCodeForm: new Form({
      validation_code: ''
    })
  }),
  computed: mapGetters({
    authenticated: 'auth/check',
    user: 'auth/user'
  }),
  mounted () {
    this.linkedAccounts = JSON.parse(this.user.linked_accounts)
    if (!this.linkedAccounts) {
      this.linkedAccounts = []
    }
  },
  methods: {
    isMainAccount () {
      return this.linkedAccounts.length && this.linkedAccounts.find(item => item.id === this.user.id).main_account
    },
    initUnlinkAccount (accountToUnlink) {
      this.accountToUnlink = accountToUnlink
      this.$bvModal.show('modal-confirm-unlink-account')
    },
    async unlinkAccount () {
      try {
        const { data } = await axios.patch(`/api/linked-account/unlink/${this.accountToUnlink.id}`)

        if (data.type === 'info') {
          this.$bvModal.hide('modal-confirm-unlink-account')
          this.$bvModal.show('modal-no-longer-linked')
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.accountToLinkToForm.errors.flatten()
          this.$bvModal.show('modal-form-link-accounts')
        }
      }
    },
    async reloadPage () {
      location.reload()
    },
    async validateCode () {
      try {
        const { data } = await this.validateCodeForm.patch('/api/linked-account/validate-code')
        if (data.type === 'success') {
          this.$bvModal.show('modal-your-accounts-have-been-linked')
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.accountToLinkToForm.errors.flatten()
        }
      }
    },
    async emailLinkToAccountCode (resent = false) {
      try {
        const { data } = await this.accountToLinkToForm.patch('/api/linked-account/email-validation-code')
        if (data.type === 'success') {
          this.$bvModal.show('modal-validate-code')
          if (resent) {
            this.$noty.info('A new code has been sent.')
          }
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.accountToLinkToForm.errors.flatten()
        }
      }
    }
  }
}
</script>
