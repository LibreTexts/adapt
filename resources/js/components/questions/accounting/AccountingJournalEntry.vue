<template>
  <div>
    <!-- Optional Prompt -->
    <div class="pb-3">
      <b-card header="default" header-html="<h2 class=&quot;h7&quot;>Prompt (Optional)</h2>">
        <b-card-text>
          <label class="mb-1"><strong>Shown to students above the journal entries:</strong></label>
          <b-form-textarea
            v-model="qtiJson.optionalPrompt"
            placeholder="Optional instructions or context for this question..."
            rows="2"
            @input="handleInput()"
          />
        </b-card-text>
      </b-card>
    </div>

    <!-- Multiple Entries Section -->
    <div class="pb-3">
      <b-card header="default" header-html="<h2 class=&quot;h7&quot;>Journal Entries</h2>">
        <b-card-text>
          <div v-for="(entry, entryIndex) in qtiJson.entries" :key="`entry-${entryIndex}`" class="pb-4">
            <b-card>
              <template #header>
                <div class="d-flex justify-content-between align-items-center">
                  <div class="d-flex align-items-center flex-grow-1">
                    <b-button
                      v-b-toggle="`entry-collapse-${entryIndex}`"
                      variant="link"
                      class="p-0 mr-2"
                      @click="handleCollapseToggle(entryIndex)"
                    >
                      <b-icon-chevron-down class="when-open"/>
                      <b-icon-chevron-right class="when-closed"/>
                    </b-button>
                    <div class="flex-grow-1">
                      <div><strong>Entry {{ entryIndex + 1 }}</strong></div>
                      <div v-if="entry.entryText || entry.entryDescription" class="text-muted small">
                        <span v-if="entry.entryText">{{ entry.entryText }}</span>
                        <span v-if="entry.entryText && entry.entryDescription"> - </span>
                        <span v-if="entry.entryDescription"
                              style="max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: bottom;"
                        >
                          {{ entry.entryDescription }}
                        </span>
                      </div>
                      <div v-show="hasBeenCollapsed[entryIndex] && getEntryErrors(entryIndex).length > 0"
                           class="collapsed-errors"
                      >
                        <small class="text-danger">{{ getEntryErrors(entryIndex).join(', ') }}</small>
                      </div>
                    </div>
                  </div>
                  <b-button
                    variant="outline-danger"
                    size="sm"
                    @click="removeEntry(entryIndex)"
                  >
                    <b-icon-trash/>
                    Remove Entry
                  </b-button>
                </div>
              </template>

              <b-collapse :id="`entry-collapse-${entryIndex}`" visible>
                <!-- Entry Text -->
                <div class="pb-3">
                  <label><strong>Entry Text (Date/Number/Letter):</strong></label>
                  <b-form-input
                    v-model="entry.entryText"
                    type="text"
                    placeholder="e.g., January 1, 2024 or Entry #1"
                    @input="clearErrors('entries', entryIndex, 'entryText'); handleInput()"
                  />
                  <ErrorMessage
                    v-if="errorKey && questionForm.errors.get(errorKey)
                      && JSON.parse(questionForm.errors.get(errorKey))['specific']
                      && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]
                      && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['entryText']"
                    :message="JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['entryText']"
                  />
                </div>

                <!-- Entry Description -->
                <div class="pb-3">
                  <label><strong>Entry Description:</strong></label>
                  <b-form-textarea
                    v-model="entry.entryDescription"
                    placeholder="Describe the transaction that occurred..."
                    rows="3"
                    @input="clearErrors('entries', entryIndex, 'entryDescription'); handleInput()"
                  />
                  <ErrorMessage
                    v-if="errorKey && questionForm.errors.get(errorKey)
                      && JSON.parse(questionForm.errors.get(errorKey))['specific']
                      && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]
                      && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['entryDescription']"
                    :message="JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['entryDescription']"
                  />
                </div>

                <!-- Solution Table -->
                <div class="pb-3">
                  <label><strong>Solution (2-5 rows):</strong></label>
                  <table class="table table-striped">
                    <thead class="nurses-table-header">
                    <tr>
                      <th scope="col" style="width: 50%">Account Title</th>
                      <th scope="col" style="width: 20%">Type</th>
                      <th scope="col" style="width: 20%">Amount</th>
                      <th scope="col" style="width: 10%">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(row, rowIndex) in (entry.solutionRows || [])"
                        :key="`entry-${entryIndex}-row-${rowIndex}`"
                    >
                      <td>
                        <b-form-input
                          v-model="row.accountTitle"
                          type="text"
                          list="account-titles-list"
                          placeholder="Start typing account name..."
                          autocomplete="off"
                          @input="clearErrors('entries', entryIndex, rowIndex, 'accountTitle'); handleInput()"
                        />
                        <datalist id="account-titles-list">
                          <option v-for="account in accountTitles" :key="account" :value="account"/>
                        </datalist>
                        <ErrorMessage
                          v-if="errorKey && questionForm.errors.get(errorKey)
                              && JSON.parse(questionForm.errors.get(errorKey))['specific']
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows']
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]['accountTitle']"
                          :message="JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]['accountTitle']"
                        />
                      </td>
                      <td>
                        <b-form-select
                          v-model="row.type"
                          :options="typeOptions"
                          @change="clearErrors('entries', entryIndex, rowIndex, 'type'); handleInput()"
                        />
                        <ErrorMessage
                          v-if="errorKey && questionForm.errors.get(errorKey)
                              && JSON.parse(questionForm.errors.get(errorKey))['specific']
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows']
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]['type']"
                          :message="JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]['type']"
                        />
                      </td>
                      <td>
                        <b-form-input
                          v-model="row.amount"
                          type="text"
                          inputmode="decimal"
                          placeholder="0.00"
                          @input="clearErrors('entries', entryIndex, rowIndex, 'amount'); handleInput()"
                        />
                        <ErrorMessage
                          v-if="errorKey && questionForm.errors.get(errorKey)
                              && JSON.parse(questionForm.errors.get(errorKey))['specific']
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows']
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]
                              && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]['amount']"
                          :message="JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows'][rowIndex]['amount']"
                        />
                      </td>
                      <td class="text-center">
                        <b-button
                          variant="outline-danger"
                          size="sm"
                          @click="deleteRow(entryIndex, rowIndex)"
                        >
                          <b-icon-trash/>
                        </b-button>
                      </td>
                    </tr>
                    </tbody>
                  </table>

                  <ErrorMessage
                    v-if="errorKey && questionForm.errors.get(errorKey)
                      && JSON.parse(questionForm.errors.get(errorKey))['specific']
                      && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]
                      && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows']
                      && JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows']['general']"
                    class="pb-2"
                    :message="JSON.parse(questionForm.errors.get(errorKey))['specific'][entryIndex]['solutionRows']['general']"
                  />

                  <div class="d-flex justify-content-between align-items-center">
                    <b-button
                      v-if="!entry.solutionRows || entry.solutionRows.length < 5"
                      class="primary"
                      size="sm"
                      @click="addRow(entryIndex)"
                    >
                      Add Row
                    </b-button>
                    <div v-if="getBalanceInfo(entryIndex).isBalanced !== null" class="ml-auto">
                      <b-alert
                        :variant="getBalanceInfo(entryIndex).isBalanced ? 'success' : 'warning'"
                        show
                        class="mb-0 d-inline-block"
                      >
                        <strong>Total Debits:</strong> ${{ formatAmount(getBalanceInfo(entryIndex).totalDebits) }} |
                        <strong>Total Credits:</strong> ${{ formatAmount(getBalanceInfo(entryIndex).totalCredits) }}
                        <span v-if="!getBalanceInfo(entryIndex).isBalanced" class="ml-2">
                          ⚠️ Entry does not balance
                        </span>
                        <span v-else class="ml-2">
                          ✓ Entry balances
                        </span>
                      </b-alert>
                    </div>
                  </div>
                </div>

                <!-- Entry Note (Optional) -->
                <div class="pb-3">
                  <div class="d-flex align-items-center mb-1">
                    <label class="mb-0"><strong>Entry Note (Optional):</strong></label>
                    <span
                      v-b-tooltip.hover
                      title="Displayed below the last row of the entry, e.g., To record sale of calculators on account."
                      class="ml-2 text-muted"
                    >
                      <b-icon-question-circle />
                    </span>
                  </div>
                  <div style="width: 50%">
                    <b-form-input
                      v-model="entry.entryNarrative"
                      type="text"
                      placeholder="Entry note..."
                      @input="handleInput()"
                    />
                  </div>
                </div>

              </b-collapse>
            </b-card>
          </div>

          <ErrorMessage
            v-if="errorKey && questionForm.errors.get(errorKey)
              && JSON.parse(questionForm.errors.get(errorKey))['general']"
            class="pb-2"
            :message="JSON.parse(questionForm.errors.get(errorKey))['general']"
          />

          <div class="d-flex justify-content-between">
            <b-button class="primary" size="sm" @click="addEntry">
              Add Entry
            </b-button>
            <div>
              <b-button variant="outline-secondary" size="sm" class="mr-2" @click="expandAll">
                Expand All
              </b-button>
              <b-button variant="outline-secondary" size="sm" @click="collapseAll">
                Collapse All
              </b-button>
            </div>
          </div>
        </b-card-text>
      </b-card>
    </div>

    <!-- T-Accounts toggle (sits outside any card so nothing renders when off) -->
    <div class="pb-3" v-if="user.id === 142886 || isAdmin">
      <b-form-checkbox
        v-model="qtiJson.includeTAccounts"
        switch
        @change="onToggleTAccounts"
      >
        Include T-Accounts for this question
      </b-form-checkbox>
    </div>

    <!-- T-Accounts Section (optional add-on) -->
    <div v-if="qtiJson.includeTAccounts" class="pb-3">
      <b-card header="default" header-html="<h2 class=&quot;h7&quot;>T-Accounts</h2>">
        <b-card-text>
          <!-- Manually add a T-Account for any account title used in the journal
               entries that doesn't already have one - whether it was never added
               (e.g. the entries were edited after this list was last reviewed) or
               was intentionally removed earlier. Every used account title gets a
               T-Account automatically by default, so this is mainly for adding
               one back, or for building a question where only some accounts
               should have a T-Account (remove the rest, keep this to re-add). -->
          <div v-if="availableAccountTitlesToAdd.length" class="pb-3 d-flex align-items-center">
            <label class="mb-0 mr-2"><strong>Create T-Account for:</strong></label>
            <b-form-select
              v-model="addTAccountSelection"
              :options="availableAccountTitlesToAdd"
              style="max-width: 320px"
              @change="addTAccountForTitle"
            />
          </div>

          <ErrorMessage
            v-if="tAccountsErrors && tAccountsErrors['tAccountsGeneral']"
            class="pb-2"
            :message="tAccountsErrors['tAccountsGeneral']"
          />

          <div v-for="{ account: tAccount, originalIndex: accountIndex } in sortedTAccountsView"
               :key="`taccount-${tAccount.identifier}`"
               class="pb-4"
          >
            <b-card>
              <template #header>
                <div class="d-flex justify-content-between align-items-center">
                  <strong class="t-account-card-title">{{ tAccount.accountTitle || '(No account selected)' }}</strong>
                  <b-button
                    variant="outline-danger"
                    size="sm"
                    @click="removeTAccount(accountIndex)"
                  >
                    <b-icon-trash/>
                    Remove Account
                  </b-button>
                </div>
              </template>

              <ErrorMessage
                v-if="tAccountsErrors && tAccountsErrors[accountIndex] && tAccountsErrors[accountIndex]['accountTitle']"
                class="pb-2"
                :message="tAccountsErrors[accountIndex]['accountTitle']"
              />

              <ErrorMessage
                v-for="message in accountFieldErrorMessages(accountIndex)"
                :key="message"
                class="pb-2"
                :message="message"
              />

              <div class="pb-2">
                  <span
                    v-b-tooltip.hover="tAccount.beginningBalance ? 'This T-Account already has a beginning balance. Remove it first to change it.' : ''"
                    tabindex="0"
                  >
                    <b-button
                      size="sm"
                      variant="outline-secondary"
                      :disabled="!!tAccount.beginningBalance"
                      @click="addBeginningBalance(accountIndex)"
                    >
                      Add Beginning Balance
                    </b-button>
                  </span>
              </div>

              <!-- Postings Table - each row is a real full row (both sides editable);
                   the instructor fills in whichever side applies and leaves the other blank. -->
              <table class="table t-account-table t-account-builder-table">
                <thead>
                <tr>
                  <th scope="col" style="width: 27%">Date/Label (Debit)</th>
                  <th scope="col" style="width: 18%">Debit Amount</th>
                  <th scope="col" style="width: 27%">Date/Label (Credit)</th>
                  <th scope="col" style="width: 18%">Credit Amount</th>
                  <th scope="col" style="width: 10%" class="text-center">Actions</th>
                </tr>
                </thead>
                <tbody>
                <!-- Beginning Balance (optional, at most one) - always first, manually
                     entered (there's no way to derive a starting balance from postings).
                     The "Beginning Balance" label is auto-derived from whichever side
                     has an amount (not both, and not manually typed) - it only ever
                     has one valid value, so there's nothing for the instructor to pick. -->
                <tr v-if="tAccount.beginningBalance" class="balance-row">
                  <td class="text-muted"><em>{{ tAccount.beginningBalance.debitLabel }}</em></td>
                  <td>
                    <b-form-input v-model="tAccount.beginningBalance.debit" type="text" inputmode="decimal" placeholder="0.00" @input="onBeginningBalanceAmountEdited(tAccount)"/>
                  </td>
                  <td class="text-muted"><em>{{ tAccount.beginningBalance.creditLabel }}</em></td>
                  <td>
                    <b-form-input v-model="tAccount.beginningBalance.credit" type="text" inputmode="decimal" placeholder="0.00" @input="onBeginningBalanceAmountEdited(tAccount)"/>
                  </td>
                  <td class="text-center action-cell">
                    <b-button variant="outline-secondary" size="sm" @click="deleteBeginningBalance(accountIndex)">
                      <b-icon-trash/>
                    </b-button>
                  </td>
                </tr>
                <tr v-for="(posting, postingIndex) in tAccount.postings"
                    :key="`posting-${posting.identifier}`"
                >
                  <td>
                    <b-form-input
                      v-model="posting.debitLabel"
                      type="text"
                      list="taccount-label-list"
                      placeholder="e.g., 6/30 or 6/30 Adj."
                      autocomplete="off"
                      @input="handleInput(); markPostingsManuallyEdited(tAccount)"
                    />
                  </td>
                  <td>
                    <b-form-input
                      v-model="posting.debit"
                      type="text"
                      inputmode="decimal"
                      placeholder="0.00"
                      @input="handleInput(); markPostingsManuallyEdited(tAccount)"
                    />
                  </td>
                  <td>
                    <b-form-input
                      v-model="posting.creditLabel"
                      type="text"
                      list="taccount-label-list"
                      placeholder="e.g., 6/30 or 6/30 Adj."
                      autocomplete="off"
                      @input="handleInput(); markPostingsManuallyEdited(tAccount)"
                    />
                  </td>
                  <td>
                    <b-form-input
                      v-model="posting.credit"
                      type="text"
                      inputmode="decimal"
                      placeholder="0.00"
                      @input="handleInput(); markPostingsManuallyEdited(tAccount)"
                    />
                  </td>
                  <td class="text-center action-cell">
                    <b-button variant="outline-secondary" size="sm" @click="deletePosting(accountIndex, postingIndex)">
                      <b-icon-trash/>
                    </b-button>
                  </td>
                </tr>
                <!-- Balance row (at most one) - both sides shown like a real row;
                     amount stays auto-calculated live until manually edited, then it
                     sticks; the label is always manually entered. -->
                <tr v-if="tAccount.balance" class="balance-row">
                  <td>
                    <b-form-input v-model="tAccount.balance.debitLabel" type="text" list="taccount-label-list" placeholder="e.g., 6/30 Bal." autocomplete="off" @input="handleInput()"/>
                  </td>
                  <td>
                    <b-form-input v-model="tAccount.balance.debit" type="text" inputmode="decimal" @input="onBalanceAmountEdited(tAccount)"/>
                  </td>
                  <td>
                    <b-form-input v-model="tAccount.balance.creditLabel" type="text" list="taccount-label-list" placeholder="e.g., 6/30 Bal." autocomplete="off" @input="handleInput()"/>
                  </td>
                  <td>
                    <b-form-input v-model="tAccount.balance.credit" type="text" inputmode="decimal" @input="onBalanceAmountEdited(tAccount)"/>
                  </td>
                  <td class="text-center action-cell">
                    <b-button variant="outline-secondary" size="sm" @click="deleteBalanceRow(accountIndex)">
                      <b-icon-trash/>
                    </b-button>
                  </td>
                </tr>
                </tbody>
              </table>
              <datalist id="taccount-label-list">
                <option v-for="label in tAccountLabelSuggestions" :key="label" :value="label"/>
              </datalist>

              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <b-button size="sm" class="mr-2" @click="addPosting(accountIndex)">
                    Add Row
                  </b-button>
                  <span
                    v-b-tooltip.hover="addBalanceTooltip(tAccount)"
                    tabindex="0"
                  >
                      <b-button
                        size="sm"
                        variant="outline-primary"
                        class="mr-2"
                        :disabled="!!tAccount.balance || filledPostingsCount(tAccount) < 1"
                        @click="addBalanceRow(accountIndex, 'debit')"
                      >
                        Add Debit Balance
                      </b-button>
                      <b-button
                        size="sm"
                        variant="outline-primary"
                        :disabled="!!tAccount.balance || filledPostingsCount(tAccount) < 1"
                        @click="addBalanceRow(accountIndex, 'credit')"
                      >
                        Add Credit Balance
                      </b-button>
                    </span>
                </div>
              </div>
            </b-card>
          </div>
        </b-card-text>
      </b-card>
    </div>
  </div>
</template>

<script>
import { v4 as uuidv4 } from 'uuid'
import ErrorMessage from '~/components/ErrorMessage'
import axios from 'axios'
import { mapGetters } from 'vuex'

export default {
  name: 'JournalEntry',
  components: { ErrorMessage },
  props: {
    qtiJson: {
      type: Object,
      default: () => ({})
    },
    questionForm: {
      type: Object,
      default: () => ({})
    }
  },
  data () {
    return {
      typeOptions: [
        { value: null, text: 'Select Type' },
        { value: 'debit', text: 'Debit' },
        { value: 'credit', text: 'Credit' }
      ],
      collapsedStates: {},
      hasBeenCollapsed: {},
      accountTitles: [],
      addTAccountSelection: null
    }
  },
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    isAdmin: () => window.config.isAdmin,
    errorKey () {
      if (this.questionForm && this.questionForm.errors) {
        if (this.questionForm.errors.get('entries')) {
          return 'entries'
        }
        if (this.questionForm.errors.get('qti_json')) {
          return 'qti_json'
        }
      }
      return null
    },
    tAccountsErrors () {
      if (!this.errorKey || !this.questionForm || !this.questionForm.errors) return null
      const raw = this.questionForm.errors.get(this.errorKey)
      if (!raw) return null
      try {
        const parsed = JSON.parse(raw)
        const combined = {}
        if (parsed.tAccountsGeneral) combined.tAccountsGeneral = parsed.tAccountsGeneral
        if (parsed.tAccounts) Object.assign(combined, parsed.tAccounts)
        return Object.keys(combined).length ? combined : null
      } catch (error) {
        return null
      }
    },
    // Alphabetical DISPLAY order for the T-Account cards - not "order first
    // used in entries," so the list itself doesn't cue students as to which
    // account comes up next in the journal entries they still have to
    // complete. This is deliberately display-only: it never reorders the
    // actual qtiJson.tAccounts array, which grading pairs with a student's
    // submitted response by array position. Sorting the real array would
    // retroactively scramble that pairing for anything a student already
    // submitted against the old order; sorting only how it's rendered here
    // is safe to apply to every existing question immediately, with nothing
    // to migrate.
    sortedTAccountsView () {
      return (this.qtiJson.tAccounts || [])
        .map((account, originalIndex) => ({ account, originalIndex }))
        .sort((a, b) =>
          (a.account.accountTitle || '').localeCompare(b.account.accountTitle || '', undefined, { sensitivity: 'base' })
        )
    },
    uniqueEntryAccountTitles () {
      const titles = []
      ;(this.qtiJson.entries || []).forEach((entry) => {
        ;(entry.solutionRows || []).forEach((row) => {
          const title = (row.accountTitle || '').trim()
          if (title && !titles.includes(title)) titles.push(title)
        })
      })
      return titles
    },
    // Any account title used in the journal entries that doesn't currently
    // have a T-Account - covers both "was removed and can be re-added" and
    // "entries changed and this title is newly used" (the watcher below
    // normally auto-adds the latter, but this is the manual fallback/override).
    availableAccountTitlesToAdd () {
      const existingTitles = (this.qtiJson.tAccounts || []).map(a => a.accountTitle)
      const options = this.uniqueEntryAccountTitles
        .filter(title => !existingTitles.includes(title))
        .map(title => ({ value: title, text: title }))
      return options.length ? [{ value: null, text: 'Select an account...' }, ...options] : []
    },
    tAccountLabelSuggestions () {
      const labels = new Set()
      ;(this.qtiJson.entries || []).forEach((entry) => {
        if (entry.entryText) labels.add(entry.entryText.trim())
      })
      ;(this.qtiJson.tAccounts || []).forEach((account) => {
        ;(account.postings || []).forEach((posting) => {
          if (posting.debitLabel) labels.add(posting.debitLabel.trim())
          if (posting.creditLabel) labels.add(posting.creditLabel.trim())
        })
        if (account.balance) {
          if (account.balance.debitLabel) labels.add(account.balance.debitLabel.trim())
          if (account.balance.creditLabel) labels.add(account.balance.creditLabel.trim())
        }
      })
      return Array.from(labels).filter(Boolean)
    }
  },
  watch: {
    uniqueEntryAccountTitles: {
      handler () {
        this.syncTAccountsFromEntries()
      }
    }
  },
  mounted () {
    this.getAccountTitles()
    if (!this.qtiJson.entries || this.qtiJson.entries.length === 0) {
      this.$set(this.qtiJson, 'entries', [
        {
          identifier: uuidv4(),
          entryText: '',
          entryDescription: '',
          entryNarrative: '',
          solutionRows: [
            { identifier: uuidv4(), accountTitle: '', type: null, amount: '' },
            { identifier: uuidv4(), accountTitle: '', type: null, amount: '' }
          ]
        }
      ])
    }
    if (!this.qtiJson.tAccounts) this.$set(this.qtiJson, 'tAccounts', [])
    if (!this.qtiJson.removedTAccountTitles) this.$set(this.qtiJson, 'removedTAccountTitles', [])
    if (this.qtiJson.includeTAccounts === undefined) this.$set(this.qtiJson, 'includeTAccounts', false)
    if (this.qtiJson.optionalPrompt === undefined) this.$set(this.qtiJson, 'optionalPrompt', '')
    // Backfill for questions saved before this snapshot existed, so opening
    // an existing question doesn't immediately flag it as "entries revised"
    // at next save - only edits made from here on will trip that warning.
    if (this.qtiJson.tAccountsConfirmedEntriesSnapshot === undefined) {
      this.$set(this.qtiJson, 'tAccountsConfirmedEntriesSnapshot', JSON.stringify(this.qtiJson.entries || []))
    }
    this.$nextTick(() => {
      this.expandEntriesWithErrors()
      this.syncTAccountsFromEntries()
    })
  },
  methods: {
    onToggleTAccounts () {
      this.syncTAccountsFromEntries()
      if (this.qtiJson.includeTAccounts) {
        // Toggling on is itself a natural checkpoint - the instructor is
        // looking at the entries right now, so treat this as "confirmed."
        this.$set(this.qtiJson, 'tAccountsConfirmedEntriesSnapshot', JSON.stringify(this.qtiJson.entries || []))
      }
      this.handleInput()
    },
    // Flattens the postings/balance/beginningBalance validation errors returned
    // by the backend into readable, row-referenced messages, since those don't
    // have their own dedicated inline display the way accountTitle does.
    //
    // Rather than echoing the backend's error text as a frozen snapshot from the
    // last failed save, each error's underlying condition is re-checked live
    // against the current data. Once the person fixes a field, the condition it
    // was flagging is no longer true, so the message drops out immediately -
    // no need to save again to clear it.
    accountFieldErrorMessages (accountIndex) {
      const errors = this.tAccountsErrors && this.tAccountsErrors[accountIndex]
      if (!errors) return []
      const tAccount = this.qtiJson.tAccounts && this.qtiJson.tAccounts[accountIndex]
      if (!tAccount) return []
      const messages = []

      if (errors.postings) {
        if (errors.postings.general) {
          if (!tAccount.postings || tAccount.postings.length === 0) {
            messages.push(errors.postings.general)
          }
        } else {
          Object.keys(errors.postings).forEach((postingIndexStr) => {
            const postingIndex = parseInt(postingIndexStr, 10)
            const posting = tAccount.postings[postingIndex]
            if (!posting) return // row was deleted since the last save - error no longer applies
            const fieldErrors = errors.postings[postingIndexStr]
            const rowNumber = postingIndex + 1

            if (fieldErrors.amount) {
              // "Enter an amount on either side" - only still applies if the row is
              // genuinely still empty on both sides.
              const hasDebit = posting.debit !== '' && posting.debit !== null
              const hasCredit = posting.credit !== '' && posting.credit !== null
              if (!hasDebit && !hasCredit) {
                messages.push(`Row ${rowNumber}: Enter an amount on either the debit or credit side.`)
              }
            }
            if (fieldErrors.debitLabel || fieldErrors.debit) {
              const freshError = this.postingSideErrorMessage(posting.debitLabel, posting.debit, 'Debit')
              if (freshError) messages.push(`Row ${rowNumber}: ${freshError}`)
            }
            if (fieldErrors.creditLabel || fieldErrors.credit) {
              const freshError = this.postingSideErrorMessage(posting.creditLabel, posting.credit, 'Credit')
              if (freshError) messages.push(`Row ${rowNumber}: ${freshError}`)
            }
          })
        }
      }
      if (errors.balance && (errors.balance.amount || errors.balance.label)) {
        const freshError = this.simpleBalanceErrorMessage(tAccount.balance, 'balance', true)
        if (freshError) messages.push(`Balance: ${freshError}`)
      }
      if (errors.beginningBalance && errors.beginningBalance.amount) {
        const freshError = this.simpleBalanceErrorMessage(tAccount.beginningBalance, 'beginning balance', false)
        if (freshError) messages.push(`Beginning Balance: ${freshError}`)
      }
      return messages
    },
    // Mirrors the backend's per-side posting validation: each side is either
    // fully used (label + amount both present) or fully unused (both blank) -
    // checked independently, since a row may legitimately hold both an
    // independent debit transaction and an independent credit transaction.
    postingSideErrorMessage (label, amount, sideName) {
      const hasLabel = !!(label && label.trim() !== '')
      const hasAmount = amount !== '' && amount !== null
      if (hasLabel && !hasAmount) return `${sideName} amount is required since a ${sideName.toLowerCase()} label was entered.`
      if (hasAmount && !hasLabel) return `${sideName} label (date/number) is required.`
      if (hasAmount) {
        const amt = this.parseAmount(amount)
        if (isNaN(amt)) return `${sideName} amount must be a valid number.`
        if (amt < 0) return `${sideName} amount cannot be negative.`
      }
      return null
    },
    // Mirrors the backend's Balance/Beginning Balance validation. Balance has a
    // real editable label (requireLabel=true); Beginning Balance stays a fixed
    // "Beginning Balance" tag with no label to validate (requireLabel=false).
    simpleBalanceErrorMessage (balance, label, requireLabel) {
      if (!balance) return null
      const hasDebit = balance.debit !== '' && balance.debit !== null
      const hasCredit = balance.credit !== '' && balance.credit !== null
      if (!hasDebit && !hasCredit) return `Enter the ${label} on either the debit or credit side.`
      if (hasDebit && hasCredit) return `Cannot be on both the debit and credit side.`
      const side = hasDebit ? 'debit' : 'credit'
      if (requireLabel) {
        const sideLabel = balance[side + 'Label']
        if (!sideLabel || sideLabel.trim() === '') return 'Label (date/number) is required.'
      }
      const amt = this.parseAmount(balance[side])
      if (isNaN(amt)) return 'Amount is required and must be a valid number.'
      if (amt < 0) return 'Amount cannot be negative.'
      return null
    },
    // Auto-creates a T-Account for every unique account title used in the journal
    // entries, unless the teacher explicitly removed it (tracked in removedTAccountTitles).
    syncTAccountsFromEntries () {
      if (!this.qtiJson.tAccounts) this.$set(this.qtiJson, 'tAccounts', [])
      if (!this.qtiJson.removedTAccountTitles) this.$set(this.qtiJson, 'removedTAccountTitles', [])
      const existingTitles = this.qtiJson.tAccounts.map(a => a.accountTitle)
      this.uniqueEntryAccountTitles.forEach((title) => {
        if (!existingTitles.includes(title) && !this.qtiJson.removedTAccountTitles.includes(title)) {
          this.qtiJson.tAccounts.push(this.newTAccount(title))
        }
      })
      // Drop T-Accounts whose account title no longer appears in any entry at all
      // (rather than one the teacher intentionally removed, which stays removable/re-addable).
      this.qtiJson.tAccounts = this.qtiJson.tAccounts.filter(a =>
        this.uniqueEntryAccountTitles.includes(a.accountTitle)
      )
      // Backfill beginningBalance for T-Accounts created before this field existed.
      this.qtiJson.tAccounts.forEach((account) => {
        if (account.beginningBalance === undefined) this.$set(account, 'beginningBalance', null)
        // Backfill debitLabel/creditLabel for beginningBalance rows created
        // before the label became a real (auto-derived) field.
        if (account.beginningBalance && account.beginningBalance.debitLabel === undefined) {
          this.onBeginningBalanceAmountEdited(account)
        }
      })
      this.resyncAutoGeneratedPostings()
      this.handleInput()
    },
    // Keeps a T-Account's postings fully derived from the journal entries -
    // one posting per debit/credit occurrence of that account, with both the
    // label and the amount filled in - for as long as the instructor hasn't
    // manually touched any posting on that account (postingsAutoGenerated).
    // The moment they do, markPostingsManuallyEdited() turns this off for
    // that account permanently, so their edits are never overwritten.
    // Accounts saved before this feature existed have no flag at all; those
    // are treated as already-manual (never retroactively regenerated), since
    // silently replacing postings an instructor already typed real amounts
    // into would be destructive.
    resyncAutoGeneratedPostings () {
      this.qtiJson.tAccounts.forEach((account) => {
        if (account.postingsAutoGenerated === undefined) {
          this.$set(account, 'postingsAutoGenerated', false)
          return
        }
        if (!account.postingsAutoGenerated) return
        account.postings = this.computeAutoPostings(account.accountTitle)
      })
    },
    markPostingsManuallyEdited (account) {
      if (account.postingsAutoGenerated) {
        this.$set(account, 'postingsAutoGenerated', false)
      }
    },
    // Builds the full auto-generated postings list for an account: every
    // journal-entry solutionRow referencing this title becomes an occurrence
    // on the debit or credit side per that row's own "type" - a debit entry
    // always posts to the debit side of the account, a credit entry to the
    // credit side, regardless of what kind of account it is (asset, expense,
    // etc.) - so no separate "account type" classification is needed to know
    // which column an amount belongs in. Debit and credit occurrences are
    // independent sequences and are simply paired up row by row (a posting
    // row's debit half and credit half don't have to be the same underlying
    // transaction - they're graded independently either way).
    computeAutoPostings (accountTitle) {
      const debitOccurrences = []
      const creditOccurrences = []
      ;(this.qtiJson.entries || []).forEach((entry) => {
        ;(entry.solutionRows || []).forEach((row) => {
          if (row.accountTitle !== accountTitle) return
          if (row.type !== 'debit' && row.type !== 'credit') return
          const occurrence = { label: (entry.entryText || '').trim(), amount: row.amount || '' }
          if (row.type === 'debit') debitOccurrences.push(occurrence)
          else creditOccurrences.push(occurrence)
        })
      })
      const rowCount = Math.max(debitOccurrences.length, creditOccurrences.length, 1)
      const postings = []
      for (let i = 0; i < rowCount; i++) {
        const debitOcc = debitOccurrences[i]
        const creditOcc = creditOccurrences[i]
        postings.push({
          identifier: uuidv4(),
          debitLabel: debitOcc ? debitOcc.label : '',
          debit: debitOcc ? debitOcc.amount : '',
          creditLabel: creditOcc ? creditOcc.label : '',
          credit: creditOcc ? creditOcc.amount : ''
        })
      }
      return postings
    },
    newTAccount (title) {
      return {
        identifier: uuidv4(),
        accountTitle: title,
        postings: this.computeAutoPostings(title),
        postingsAutoGenerated: true,
        beginningBalance: null,
        balance: null
      }
    },
    removeTAccount (accountIndex) {
      const account = this.qtiJson.tAccounts[accountIndex]
      if (!account) return
      if (!this.qtiJson.removedTAccountTitles.includes(account.accountTitle)) {
        this.qtiJson.removedTAccountTitles.push(account.accountTitle)
      }
      this.qtiJson.tAccounts.splice(accountIndex, 1)
      this.handleInput()
    },
    addTAccountForTitle (title) {
      if (!title) return
      const idx = this.qtiJson.removedTAccountTitles.indexOf(title)
      if (idx !== -1) this.qtiJson.removedTAccountTitles.splice(idx, 1)
      if (!this.qtiJson.tAccounts.some(a => a.accountTitle === title)) {
        this.qtiJson.tAccounts.push(this.newTAccount(title))
      }
      this.addTAccountSelection = null
      this.handleInput()
    },
    // Adds a real full row - both sides are editable; the instructor fills in
    // whichever side applies to this posting and leaves the other blank.
    // Neither label is pre-filled: a label with no matching amount (or vice
    // versa) is invalid, so guessing a label the instructor might not use
    // would just create an error they'd have to clear manually. Manually
    // adding a row means the instructor is now curating postings themselves,
    // so auto-regeneration stops touching this account from here on.
    addPosting (accountIndex) {
      const account = this.qtiJson.tAccounts[accountIndex]
      account.postings.push({
        identifier: uuidv4(),
        debitLabel: '',
        debit: '',
        creditLabel: '',
        credit: ''
      })
      this.markPostingsManuallyEdited(account)
      this.handleInput()
      this.$forceUpdate()
    },
    deletePosting (accountIndex, postingIndex) {
      const account = this.qtiJson.tAccounts[accountIndex]
      if (account.postings.length <= 1) {
        this.$noty.info('You need at least one posting for a T-Account.')
        return
      }
      account.postings.splice(postingIndex, 1)
      this.markPostingsManuallyEdited(account)
      this.handleInput()
    },
    // Enabling condition for "Add Balance": at least one NUMBER entered (an
    // account with a single posting can still validly have an ending balance
    // equal to that number - it's just optional, never required). Counts each
    // filled amount individually (a single row with both a debit and credit
    // number counts as 2), not just how many rows have any data. Includes the
    // Beginning Balance, since it genuinely factors into the ending balance.
    filledPostingsCount (tAccount) {
      let count = 0
      tAccount.postings.forEach((p) => {
        if (p.debit !== '') count++
        if (p.credit !== '') count++
      })
      if (tAccount.beginningBalance) {
        if (tAccount.beginningBalance.debit !== '') count++
        if (tAccount.beginningBalance.credit !== '') count++
      }
      return count
    },
    addBalanceTooltip (tAccount) {
      if (tAccount.balance) return 'This T-Account already has a balance. Remove it first to recalculate.'
      if (this.filledPostingsCount(tAccount) < 1) return 'Enter at least 1 number before adding a balance.'
      return ''
    },
    // The magnitude is the same regardless of which side the instructor chooses -
    // |total debits - total credits|, where the Beginning Balance (if present)
    // counts as an amount on its own side, same as a normal posting would.
    // The instructor determines which side it belongs on (Debit Balance vs
    // Credit Balance) via which button they click.
    computeBalanceAmount (tAccount) {
      let totalDebits = 0
      let totalCredits = 0
      tAccount.postings.forEach((p) => {
        if (p.debit !== '') totalDebits += this.parseAmount(p.debit)
        if (p.credit !== '') totalCredits += this.parseAmount(p.credit)
      })
      if (tAccount.beginningBalance) {
        if (tAccount.beginningBalance.debit !== '') totalDebits += this.parseAmount(tAccount.beginningBalance.debit)
        if (tAccount.beginningBalance.credit !== '') totalCredits += this.parseAmount(tAccount.beginningBalance.credit)
      }
      return Math.abs(totalDebits - totalCredits)
    },
    addBalanceRow (accountIndex, side) {
      const account = this.qtiJson.tAccounts[accountIndex]
      if (account.balance) return
      const amount = this.computeBalanceAmount(account).toFixed(2)
      account.balance = {
        side,
        debitLabel: '',
        debit: side === 'debit' ? amount : '',
        creditLabel: '',
        credit: side === 'credit' ? amount : '',
        edited: false
      }
      this.handleInput()
      this.$forceUpdate()
    },
    // Marks the balance amount as manually overridden so it stops being
    // recalculated automatically as postings change (their typed value sticks).
    onBalanceAmountEdited (tAccount) {
      tAccount.balance.edited = true
      this.handleInput()
    },
    deleteBalanceRow (accountIndex) {
      const account = this.qtiJson.tAccounts[accountIndex]
      account.balance = null
      this.handleInput()
    },
    // Beginning Balance is always manually entered - there's no way to derive a
    // starting balance from the postings, unlike the ending balance. Both sides
    // are shown editable, same as a normal row; the instructor just types into
    // whichever side applies (and students have to figure out which, too).
    addBeginningBalance (accountIndex) {
      const account = this.qtiJson.tAccounts[accountIndex]
      if (account.beginningBalance) return
      account.beginningBalance = {
        debitLabel: '',
        debit: '',
        creditLabel: '',
        credit: ''
      }
      this.handleInput()
      this.$forceUpdate()
    },
    // "Beginning Balance" is the only valid label a beginning balance can ever
    // have, so it's derived automatically from whichever side has an amount
    // rather than typed/picked by the instructor - and cleared on the side
    // that's blank, so it's never shown on both sides at once.
    onBeginningBalanceAmountEdited (tAccount) {
      const bb = tAccount.beginningBalance
      bb.debitLabel = bb.debit !== '' ? 'Beginning Balance' : ''
      bb.creditLabel = bb.credit !== '' ? 'Beginning Balance' : ''
      this.handleInput()
    },
    deleteBeginningBalance (accountIndex) {
      const account = this.qtiJson.tAccounts[accountIndex]
      account.beginningBalance = null
      this.handleInput()
    },
    parseAmount (value) {
      if (value === null || value === undefined || value === '') {
        return 0
      }
      return parseFloat(value.toString().replace(/,/g, '')) || 0
    },
    async getAccountTitles () {
      try {
        const { data } = await axios.get('/api/questions/valid-accounting-journal-entries')
        this.accountTitles = data
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    clearErrors (key, entryIndex = null, rowIndexOrField = null, field = null) {
      if (!this.questionForm || !this.questionForm.errors || !this.questionForm.errors.get) {
        return
      }
      const errorKey = key === 'entries'
        ? (this.questionForm.errors.get(errorKey) ? 'entries' : 'qti_json')
        : key
      try {
        const errors = this.questionForm.errors.get(errorKey)
        if (!errors) return
        const parsedErrors = JSON.parse(errors)
        if (errorKey === 'entries' || errorKey === 'qti_json') {
          if (entryIndex !== null) {
            if (!parsedErrors.specific || !parsedErrors.specific[entryIndex]) return
            if (typeof rowIndexOrField === 'number' && field) {
              const rowIndex = rowIndexOrField
              if (parsedErrors.specific[entryIndex].solutionRows &&
                parsedErrors.specific[entryIndex].solutionRows[rowIndex]) {
                delete parsedErrors.specific[entryIndex].solutionRows[rowIndex][field]
                if (Object.keys(parsedErrors.specific[entryIndex].solutionRows[rowIndex]).length === 0) {
                  delete parsedErrors.specific[entryIndex].solutionRows[rowIndex]
                }
                if (parsedErrors.specific[entryIndex].solutionRows &&
                  Object.keys(parsedErrors.specific[entryIndex].solutionRows).length === 0) {
                  delete parsedErrors.specific[entryIndex].solutionRows
                }
              }
            } else {
              const fieldName = rowIndexOrField
              if (parsedErrors.specific[entryIndex][fieldName]) {
                delete parsedErrors.specific[entryIndex][fieldName]
              }
            }
            if (Object.keys(parsedErrors.specific[entryIndex]).length === 0) {
              delete parsedErrors.specific[entryIndex]
            }
            if (parsedErrors.specific && Object.keys(parsedErrors.specific).length === 0) {
              delete parsedErrors.specific
            }
          }
        }
        this.questionForm.errors.set(errorKey, JSON.stringify(parsedErrors))
        this.$forceUpdate()
      } catch (error) {
        console.error('Error clearing errors:', error)
      }
    },
    handleInput () {
      // Keep every account's balance live-synced to the current postings,
      // unless the instructor has manually edited it - once they've typed
      // their own value, it sticks and stops auto-recalculating. The side
      // (debit vs credit) is the instructor's own choice and never changes
      // automatically - only the amount recalculates.
      ;(this.qtiJson.tAccounts || []).forEach((account) => {
        if (account.balance && !account.balance.edited) {
          const amount = this.computeBalanceAmount(account).toFixed(2)
          if (account.balance.side === 'debit') {
            account.balance.debit = amount
            account.balance.credit = ''
          } else {
            account.balance.credit = amount
            account.balance.debit = ''
          }
        }
      })
      this.$emit('update-qti-json', 'entries', this.qtiJson.entries)
      this.$emit('update-qti-json', 'includeTAccounts', this.qtiJson.includeTAccounts)
      this.$emit('update-qti-json', 'tAccounts', this.qtiJson.tAccounts)
      this.$emit('update-qti-json', 'removedTAccountTitles', this.qtiJson.removedTAccountTitles)
      this.$emit('update-qti-json', 'optionalPrompt', this.qtiJson.optionalPrompt)
    },
    expandEntriesWithErrors () {
      if (!this.questionForm || !this.questionForm.errors || !this.questionForm.errors.get) {
        return
      }
      try {
        let entriesErrors = this.questionForm.errors.get('entries')
        if (!entriesErrors) {
          entriesErrors = this.questionForm.errors.get('qti_json')
        }
        if (!entriesErrors) return
        const parsedErrors = JSON.parse(entriesErrors)
        if (!parsedErrors.specific) return
        Object.keys(parsedErrors.specific).forEach(entryIndex => {
          const index = parseInt(entryIndex)
          this.$set(this.hasBeenCollapsed, index, true)
          this.$root.$emit('bv::toggle::collapse', `entry-collapse-${index}`)
        })
      } catch (error) {
        console.error('Error expanding entries with errors:', error)
      }
    },
    handleCollapseToggle (entryIndex) {
      this.$set(this.hasBeenCollapsed, entryIndex, true)
    },
    getEntryErrors (entryIndex) {
      const errors = []
      const entry = this.qtiJson.entries[entryIndex]
      if (!entry) return errors
      if (!entry.entryText || entry.entryText.trim() === '') {
        errors.push('Missing entry text')
      }
      if (!entry.entryDescription || entry.entryDescription.trim() === '') {
        errors.push('Missing description')
      }
      if (entry.solutionRows && entry.solutionRows.length > 0) {
        let rowErrorCount = 0
        entry.solutionRows.forEach((row) => {
          let rowHasError = false
          if (!row.accountTitle || row.accountTitle.trim() === '') rowHasError = true
          if (!row.type) rowHasError = true
          if (!row.amount || row.amount === '' || this.parseAmount(row.amount) < 0) rowHasError = true
          if (rowHasError) rowErrorCount++
        })
        if (rowErrorCount > 0) {
          errors.push(`${rowErrorCount} row${rowErrorCount > 1 ? 's have' : ' has'} missing fields`)
        }
        const balanceInfo = this.getBalanceInfo(entryIndex)
        if (balanceInfo.isBalanced === false) {
          errors.push('Entry does not balance')
        }
      } else {
        errors.push('Missing solution rows')
      }
      return errors
    },
    getBalanceInfo (entryIndex) {
      let totalDebits = 0
      let totalCredits = 0
      let hasAnyValues = false
      const entry = this.qtiJson.entries[entryIndex]
      if (!entry || !entry.solutionRows || !Array.isArray(entry.solutionRows)) {
        return { totalDebits: 0, totalCredits: 0, isBalanced: null }
      }
      entry.solutionRows.forEach((row) => {
        const amount = this.parseAmount(row.amount)
        if (row.amount !== '' && row.amount !== null && row.amount !== undefined && row.type) {
          hasAnyValues = true
          if (row.type === 'debit') totalDebits += amount
          else if (row.type === 'credit') totalCredits += amount
        }
      })
      return {
        totalDebits,
        totalCredits,
        isBalanced: hasAnyValues ? Math.abs(totalDebits - totalCredits) < 0.01 : null
      }
    },
    addEntry () {
      if (!this.qtiJson.entries) {
        this.$set(this.qtiJson, 'entries', [])
      }
      this.qtiJson.entries.push({
        identifier: uuidv4(),
        entryText: '',
        entryDescription: '',
        entryNarrative: '',
        solutionRows: [
          { identifier: uuidv4(), accountTitle: '', type: null, amount: '' },
          { identifier: uuidv4(), accountTitle: '', type: null, amount: '' }
        ]
      })
      this.$emit('update-qti-json', 'entries', this.qtiJson.entries)
      this.$forceUpdate()
    },
    removeEntry (entryIndex) {
      if (this.qtiJson.entries.length === 1) {
        this.$noty.info('You need at least one entry.')
        return
      }
      this.qtiJson.entries.splice(entryIndex, 1)
      this.$emit('update-qti-json', 'entries', this.qtiJson.entries)
    },
    addRow (entryIndex) {
      const entry = this.qtiJson.entries[entryIndex]
      if (!entry.solutionRows) {
        this.$set(entry, 'solutionRows', [])
      }
      if (entry.solutionRows.length < 5) {
        entry.solutionRows.push({
          identifier: uuidv4(),
          accountTitle: '',
          type: null,
          amount: ''
        })
        this.$emit('update-qti-json', 'entries', this.qtiJson.entries)
        this.$forceUpdate()
      } else {
        this.$noty.info('Maximum of 5 rows allowed.')
      }
    },
    deleteRow (entryIndex, rowIndex) {
      const entry = this.qtiJson.entries[entryIndex]
      if (!entry.solutionRows) return
      if (entry.solutionRows.length <= 2) {
        this.$noty.info('You need at least two rows for a journal entry.')
        return
      }
      entry.solutionRows.splice(rowIndex, 1)
      this.$emit('update-qti-json', 'entries', this.qtiJson.entries)
    },
    expandAll () {
      this.qtiJson.entries.forEach((entry, index) => {
        this.$root.$emit('bv::toggle::collapse', `entry-collapse-${index}`)
      })
    },
    collapseAll () {
      this.qtiJson.entries.forEach((entry, index) => {
        this.$set(this.hasBeenCollapsed, index, true)
        this.$root.$emit('bv::toggle::collapse', `entry-collapse-${index}`)
      })
    },
    formatAmount (amount) {
      return amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    }
  }
}
</script>

<style scoped>
.nurses-table-header {
  background-color: #f8f9fa;
}

.table {
  margin-bottom: 1rem;
}

input[type="number"]:disabled {
  background-color: #e9ecef;
  cursor: not-allowed;
}

.collapsed > .when-open,
.not-collapsed > .when-closed {
  display: none;
}

.collapsed-errors {
  margin-top: 0.25rem;
}

.t-account-table,
.t-account-table th,
.t-account-table td {
  border: none;
}

.t-account-card-title {
  color: #2c3e63;
}

.t-account-table thead th {
  border-bottom: 2px solid #dee2e6 !important;
  background-color: #f8f9fa;
  color: #495057;
  text-align: center;
  font-weight: 600;
}

.t-account-table thead th:nth-child(2),
.t-account-table tbody td:nth-child(2) {
  border-right: 2px solid #dee2e6 !important;
}

.t-account-table tbody tr td {
  vertical-align: middle;
  padding: 0.4rem 0.5rem;
}

.t-account-table .balance-row td {
  border-top: 2px solid #dee2e6 !important;
  padding-top: 0.6rem;
}

.t-account-builder-table .action-cell {
  border-right: none !important;
  width: 60px;
}
</style>
