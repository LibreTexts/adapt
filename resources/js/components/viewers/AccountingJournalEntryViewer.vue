<template>
  <div class="pb-2">
    <!-- Optional Prompt -->
    <div v-if="qtiJson.optionalPrompt && qtiJson.optionalPrompt.trim()" class="pb-3">
      <p class="mb-0">{{ escapeDollar(qtiJson.optionalPrompt) }}</p>
    </div>

    <!-- Entry Instructions - Verbal Information -->
    <div v-if="qtiJson.entries && qtiJson.entries.length > 0" class="instructions-section pb-4 mb-4">
      <h5 class="mb-3">Journal Entry Descriptions:</h5>
      <div v-for="(entry, entryIndex) in qtiJson.entries" :key="`entry-instruction-${entryIndex}`" class="pb-2">
        <strong>{{ escapeDollar(entry.entryText) }}:</strong> {{ escapeDollar(entry.entryDescription) }}
      </div>
    </div>

    <hr class="section-divider">

    <!-- Student Work Area - Single Table for All Entries -->
    <div class="student-work-section">
      <h5 class="mb-3">Complete the Journal Entries:</h5>

      <table class="table table-bordered journal-entry-table">
        <thead class="table-header">
        <tr>
          <th scope="col" style="width: 20%">Entry</th>
          <th scope="col" style="width: 35%">Account Title</th>
          <th scope="col" style="width: 22.5%">Debit</th>
          <th scope="col" style="width: 22.5%">Credit</th>
        </tr>
        </thead>
        <tbody>
        <template v-for="(entry, entryIndex) in studentEntries">
          <tr v-for="(row, rowIndex) in entry.rows"
              :key="`entry-${entryIndex}-row-${rowIndex}`"
              :class="{'entry-divider': rowIndex === 0 && entryIndex > 0}"
          >
            <!-- Entry selection dropdown only on first row of each entry -->
            <td v-if="rowIndex === 0"
                :rowspan="entry.rows.length"
                class="entry-cell"
            >
              <b-form-select
                v-model="entry.selectedEntryIndex"
                :options="getEntryOptionsFor(entryIndex)"
                size="sm"
                :class="[getEntryCellClass(entryIndex), {'is-incomplete': isIncomplete(entryIndex, null, 'entry')}]"
                @change="clearEntryColor(entryIndex)"
              />
            </td>

            <td>
              <b-form-input
                v-model="row.accountTitle"
                type="text"
                list="account-titles-list"
                placeholder="Start typing account title..."
                autocomplete="off"
                size="sm"
                :class="[getFieldClass(entryIndex, rowIndex, 'accountTitle'), {'account-indent': isCreditRow(entryIndex, rowIndex)}, {'is-incomplete': isIncomplete(entryIndex, rowIndex, 'accountTitle')}]"
                @input="clearFieldColor(entryIndex, rowIndex, 'accountTitle')"
              />
              <datalist id="account-titles-list">
                <option v-for="account in accountTitles" :key="account" :value="account"/>
              </datalist>
              <!-- Narrative shown below the account title of the last row -->
              <div
                v-if="rowIndex === entry.rows.length - 1 && getEntryNarrative(entryIndex)"
                class="entry-narrative"
              >
                {{ escapeDollar(getEntryNarrative(entryIndex)) }}
              </div>
            </td>
            <td>
              <b-form-input
                v-model="row.debit"
                type="text"
                inputmode="decimal"
                placeholder=""
                size="sm"
                class="amount-input"
                :class="[getFieldClass(entryIndex, rowIndex, 'debit'), {'is-incomplete': isIncomplete(entryIndex, rowIndex, 'debit')}]"
                @input="onAmountInput(entryIndex, rowIndex, 'debit')"
              />
            </td>
            <td>
              <b-form-input
                v-model="row.credit"
                type="text"
                inputmode="decimal"
                placeholder=""
                size="sm"
                class="amount-input"
                :class="[getFieldClass(entryIndex, rowIndex, 'credit'), {'is-incomplete': isIncomplete(entryIndex, rowIndex, 'credit')}]"
                @input="onAmountInput(entryIndex, rowIndex, 'credit')"
              />
            </td>
          </tr>
        </template>
        </tbody>
      </table>
    </div>

    <!-- T-Accounts Work Area -->
    <div v-if="qtiJson.includeTAccounts && qtiJson.tAccounts && qtiJson.tAccounts.length"
         class="t-accounts-section pt-4"
    >
      <hr class="section-divider">
      <h5 class="mb-3">Post to the T-Accounts:</h5>

      <div v-for="(account, accountIndex) in qtiJson.tAccounts"
           v-if="studentTAccounts[accountIndex]"
           :key="`taccount-${accountIndex}`"
           class="t-account-block"
      >
        <h6 class="t-account-title">{{ escapeDollar(account.accountTitle) }}</h6>
        <table class="table t-account-table">
          <thead>
          <tr>
            <th scope="col" style="width: 30%">Date/Label</th>
            <th scope="col" style="width: 20%">Debit</th>
            <th scope="col" style="width: 30%">Date/Label</th>
            <th scope="col" style="width: 20%">Credit</th>
          </tr>
          </thead>
          <tbody>
          <tr v-if="studentTAccounts[accountIndex].beginningBalance" class="balance-row">
            <td class="text-muted"><em>Beginning Balance</em></td>
            <td>
              <b-form-input
                v-model="studentTAccounts[accountIndex].beginningBalance.debit"
                type="text"
                inputmode="decimal"
                size="sm"
                class="amount-input"
                :class="[getTAccountBeginningBalanceFieldClass(accountIndex, 'debit'), {'is-incomplete': isTAccountBeginningBalanceIncomplete(accountIndex, 'debit')}]"
                @input="hasStartedEditing = true"
              />
            </td>
            <td class="text-muted"><em>Beginning Balance</em></td>
            <td>
              <b-form-input
                v-model="studentTAccounts[accountIndex].beginningBalance.credit"
                type="text"
                inputmode="decimal"
                size="sm"
                class="amount-input"
                :class="[getTAccountBeginningBalanceFieldClass(accountIndex, 'credit'), {'is-incomplete': isTAccountBeginningBalanceIncomplete(accountIndex, 'credit')}]"
                @input="hasStartedEditing = true"
              />
            </td>
          </tr>
          <tr v-for="(row, rowIndex) in studentTAccounts[accountIndex].rows"
              :key="`taccount-${accountIndex}-row-${rowIndex}`"
          >
            <td>
              <b-form-select
                v-model="row.debitLabel"
                :options="tAccountLabelOptions"
                size="sm"
                :class="[getTAccountFieldClass(accountIndex, rowIndex, 'debitLabel'), {'is-incomplete': isTAccountFieldIncomplete(accountIndex, rowIndex, 'debitLabel')}]"
                @change="hasStartedEditing = true"
              />
            </td>
            <td>
              <b-form-input
                v-model="row.debit"
                type="text"
                inputmode="decimal"
                size="sm"
                class="amount-input"
                :class="[getTAccountFieldClass(accountIndex, rowIndex, 'debit'), {'is-incomplete': isTAccountFieldIncomplete(accountIndex, rowIndex, 'debit')}]"
                @input="hasStartedEditing = true"
              />
            </td>
            <td>
              <b-form-select
                v-model="row.creditLabel"
                :options="tAccountLabelOptions"
                size="sm"
                :class="[getTAccountFieldClass(accountIndex, rowIndex, 'creditLabel'), {'is-incomplete': isTAccountFieldIncomplete(accountIndex, rowIndex, 'creditLabel')}]"
                @change="hasStartedEditing = true"
              />
            </td>
            <td>
              <b-form-input
                v-model="row.credit"
                type="text"
                inputmode="decimal"
                size="sm"
                class="amount-input"
                :class="[getTAccountFieldClass(accountIndex, rowIndex, 'credit'), {'is-incomplete': isTAccountFieldIncomplete(accountIndex, rowIndex, 'credit')}]"
                @input="hasStartedEditing = true"
              />
            </td>
          </tr>
          <tr v-if="studentTAccounts[accountIndex].balance" class="balance-row">
            <td>
              <b-form-select
                v-model="studentTAccounts[accountIndex].balance.debitLabel"
                :options="tAccountLabelOptions"
                size="sm"
                :class="[getTAccountBalanceFieldClass(accountIndex, 'debitLabel'), {'is-incomplete': isTAccountBalanceIncomplete(accountIndex, 'debitLabel')}]"
                @change="hasStartedEditing = true"
              />
            </td>
            <td>
              <b-form-input
                v-model="studentTAccounts[accountIndex].balance.debit"
                type="text"
                inputmode="decimal"
                size="sm"
                class="amount-input"
                :class="[getTAccountBalanceFieldClass(accountIndex, 'debit'), {'is-incomplete': isTAccountBalanceIncomplete(accountIndex, 'debit')}]"
                @input="hasStartedEditing = true"
              />
            </td>
            <td>
              <b-form-select
                v-model="studentTAccounts[accountIndex].balance.creditLabel"
                :options="tAccountLabelOptions"
                size="sm"
                :class="[getTAccountBalanceFieldClass(accountIndex, 'creditLabel'), {'is-incomplete': isTAccountBalanceIncomplete(accountIndex, 'creditLabel')}]"
                @change="hasStartedEditing = true"
              />
            </td>
            <td>
              <b-form-input
                v-model="studentTAccounts[accountIndex].balance.credit"
                type="text"
                inputmode="decimal"
                size="sm"
                class="amount-input"
                :class="[getTAccountBalanceFieldClass(accountIndex, 'credit'), {'is-incomplete': isTAccountBalanceIncomplete(accountIndex, 'credit')}]"
                @input="hasStartedEditing = true"
              />
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Debug panel: student's answer vs. the correct answer, box by box.
         Only shows when grading results already exist - i.e. only in contexts
         where the solution has already been revealed to this viewer, so this
         adds readability rather than exposing anything new. -->
    <div v-if="qtiJson.includeTAccounts && parsedTAccountGradingResults && environment=== 'local'"
         class="t-account-debug-panel mt-4"
    >
      <h6 class="text-muted">Debug: Student Answer vs. Correct Answer (T-Accounts)</h6>
      <table v-for="(account, accountIndex) in qtiJson.tAccounts"
             :key="`debug-taccount-${accountIndex}`"
             class="table table-sm table-bordered debug-table mb-3"
      >
        <caption class="caption-top font-weight-bold">{{ escapeDollar(account.accountTitle) }}</caption>
        <thead>
        <tr>
          <th>Box</th>
          <th>Student Answer</th>
          <th>Correct Answer</th>
          <th>Match?</th>
        </tr>
        </thead>
        <tbody>
        <template v-for="row in tAccountDebugRows(accountIndex)">
          <tr :key="row.box"
              :class="row.isCorrect === false ? 'table-danger' : (row.isCorrect === true ? 'table-success' : '')"
          >
            <td>{{ row.box }}</td>
            <td>{{ row.student }}</td>
            <td>{{ row.correct }}</td>
            <td>{{ row.isCorrect === null ? 'n/a' : (row.isCorrect ? '✓' : '✗') }}</td>
          </tr>
        </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { escapeDollar } from '../../helpers/MathJax'

export default {
  name: 'AccountingJournalEntryViewer',
  props: {
    qtiJson: {
      type: Object,
      default: () => ({})
    },
    studentResponse: {
      type: [Array, String],
      default: null
    }
  },
  data () {
    return {
      environment: window.config.environment,
      studentEntries: [],
      studentTAccounts: [],
      hasStartedEditing: false,
      accountTitles: [],
      indentTracker: 0
    }
  },
  computed: {
    entryOptions () {
      const options = [{ value: null, text: 'Select an entry...' }]
      if (this.qtiJson.entries) {
        this.qtiJson.entries.forEach((entry, index) => {
          options.push({ value: index, text: this.escapeDollar(entry.entryText) })
        })
      }
      return options
    },
    // Raw response can either be the legacy shape (a bare array of entries, pre-T-Accounts)
    // or the current shape { entries: [...], tAccounts: [...] }.
    parsedGradingResults () {
      let response = this.qtiJson.studentResponse
      if (typeof response === 'string') {
        try {
          response = JSON.parse(response)
        } catch (error) {
          return null
        }
      }
      if (Array.isArray(response)) return response
      return response && response.entries ? response.entries : null
    },
    parsedTAccountGradingResults () {
      let response = this.qtiJson.studentResponse
      if (typeof response === 'string') {
        try {
          response = JSON.parse(response)
        } catch (error) {
          return null
        }
      }
      return response && response.tAccounts ? response.tAccounts : null
    },
    tAccountLabelSuggestions () {
      const labels = new Set()
      if (this.qtiJson.entries) {
        this.qtiJson.entries.forEach((entry) => {
          if (entry.entryText) labels.add(entry.entryText.trim())
        })
      }
      if (this.qtiJson.tAccounts) {
        this.qtiJson.tAccounts.forEach((account) => {
          ;(account.postings || []).forEach((posting) => {
            if (posting.debitLabel) labels.add(posting.debitLabel.trim())
            if (posting.creditLabel) labels.add(posting.creditLabel.trim())
          })
          // The Balance row has its own editable label too (Beginning Balance
          // doesn't - it's always a fixed "Beginning Balance" tag) - without
          // this, a label used only on a Balance row wouldn't match any
          // <option>, and a native <select> can't display a value that isn't
          // one of its options, so it would render blank despite being correct.
          if (account.balance) {
            if (account.balance.debitLabel) labels.add(account.balance.debitLabel.trim())
            if (account.balance.creditLabel) labels.add(account.balance.creditLabel.trim())
          }
        })
      }
      return Array.from(labels).filter(Boolean)
    },
    tAccountLabelOptions () {
      return [
        { value: '', text: 'Select...' },
        ...this.tAccountLabelSuggestions.map(label => ({ value: label, text: label }))
      ]
    },
    showValidationWarning () {
      if (!this.studentEntries || this.studentEntries.length === 0) return false
      for (const entry of this.studentEntries) {
        if (entry.selectedEntryIndex === null) return true
        for (const row of entry.rows) {
          if (!row.accountTitle || row.accountTitle.trim() === '') return true
          if ((!row.debit || row.debit === '') && (!row.credit || row.credit === '')) return true
        }
      }
      return false
    },
    isComplete () {
      return !this.showValidationWarning && (!this.qtiJson.includeTAccounts || !this.tAccountsIncomplete)
    },
    tAccountsIncomplete () {
      if (!this.qtiJson.includeTAccounts) return false
      for (const account of this.studentTAccounts) {
        for (const row of account.rows) {
          const hasDebit = row.debit !== '' && row.debit !== null
          const hasCredit = row.credit !== '' && row.credit !== null
          if (!hasDebit && !hasCredit) return true
          if (hasDebit && (!row.debitLabel || row.debitLabel.trim() === '')) return true
          if (hasCredit && (!row.creditLabel || row.creditLabel.trim() === '')) return true
        }
        if (account.balance) {
          const hasDebit = account.balance.debit !== '' && account.balance.debit !== null
          const hasCredit = account.balance.credit !== '' && account.balance.credit !== null
          if (!hasDebit && !hasCredit) return true
          if (hasDebit && (!account.balance.debitLabel || account.balance.debitLabel.trim() === '')) return true
          if (hasCredit && (!account.balance.creditLabel || account.balance.creditLabel.trim() === '')) return true
        }
        if (account.beginningBalance && (!account.beginningBalance.debit || account.beginningBalance.debit === '') && (!account.beginningBalance.credit || account.beginningBalance.credit === '')) {
          return true
        }
      }
      return false
    }
  },
  mounted () {
    this.getAccountTitles()
    this.initializeStudentEntries()
    this.initializeStudentTAccounts()
    this.loadStudentResponse()
    this.loadStudentTAccountResponse()
  },
  watch: {
    // This component is reused across different questions inside the answer-
    // preview modal (mounted() only fires once per component instance, not
    // per question), so studentTAccounts/studentEntries must be rebuilt
    // whenever the underlying question actually changes - otherwise they hold
    // stale data from the previous question (e.g. a different tAccounts
    // length), which crashes any direct studentTAccounts[accountIndex] access.
    qtiJson: {
      handler () {
        this.initializeStudentEntries()
        this.initializeStudentTAccounts()
        this.loadStudentResponse()
        this.loadStudentTAccountResponse()
      },
      deep: false
    },
    studentResponse () {
      this.loadStudentResponse()
      this.loadStudentTAccountResponse()
    }
  },
  methods: {
    escapeDollar,
    // Builds a flat, readable "box by box" comparison of the student's answer
    // against the correct answer for one T-Account, for the debug panel.
    // Every graded box gets its own line with its own independent verdict -
    // label correctness is never bundled with amount correctness (or side),
    // so a wrong label doesn't make a correct amount display as wrong too.
    tAccountDebugRows (accountIndex) {
      const account = this.qtiJson.tAccounts && this.qtiJson.tAccounts[accountIndex]
      const result = this.parsedTAccountGradingResults && this.parsedTAccountGradingResults[accountIndex]
      if (!account || !result) return []
      const rows = []
      const blank = (v) => (v === '' || v === null || v === undefined ? '(blank)' : v)
      const studentSideOf = (obj) => (obj.debit !== '' ? 'debit' : (obj.credit !== '' ? 'credit' : '(blank)'))
      const correctSideOf = (obj) => (obj.debit !== '' ? 'debit' : 'credit')

      ;(account.postings || []).forEach((posting, i) => {
        const studentRow = (result.rows && result.rows[i]) || {}
        // Include a side whenever the backend actually graded it (its
        // correctness key is genuinely true/false, not null) - not just when
        // the solution uses it. A stray entry on a side the solution doesn't
        // use still gets graded (and penalized) by the backend, and needs to
        // show up here too, or it looks like it was ignored.
        if (posting.debit !== '' || studentRow.debitLabelCorrect !== null || studentRow.debitCorrect !== null) {
          rows.push({
            box: `Row ${i + 1} - Debit Label`,
            student: blank(studentRow.debitLabel),
            correct: blank(posting.debitLabel),
            isCorrect: studentRow.debitLabelCorrect === undefined ? null : studentRow.debitLabelCorrect
          })
          rows.push({
            box: `Row ${i + 1} - Debit Amount`,
            student: blank(studentRow.debit),
            correct: blank(posting.debit),
            isCorrect: studentRow.debitCorrect === undefined ? null : studentRow.debitCorrect
          })
        }
        if (posting.credit !== '' || studentRow.creditLabelCorrect !== null || studentRow.creditCorrect !== null) {
          rows.push({
            box: `Row ${i + 1} - Credit Label`,
            student: blank(studentRow.creditLabel),
            correct: blank(posting.creditLabel),
            isCorrect: studentRow.creditLabelCorrect === undefined ? null : studentRow.creditLabelCorrect
          })
          rows.push({
            box: `Row ${i + 1} - Credit Amount`,
            student: blank(studentRow.credit),
            correct: blank(posting.credit),
            isCorrect: studentRow.creditCorrect === undefined ? null : studentRow.creditCorrect
          })
        }
      })

      if (account.beginningBalance && result.beginningBalance) {
        const correctSide = correctSideOf(account.beginningBalance)
        rows.push({
          box: 'Beginning Balance - Side',
          student: studentSideOf(result.beginningBalance),
          correct: correctSide,
          isCorrect: result.beginningBalance.sideCorrect
        })
        rows.push({
          box: 'Beginning Balance - Amount',
          student: blank(result.beginningBalance[correctSide]),
          correct: blank(account.beginningBalance[correctSide]),
          isCorrect: result.beginningBalance.amountCorrect
        })
      }

      if (account.balance && result.balance) {
        const correctSide = correctSideOf(account.balance)
        const labelField = correctSide + 'Label'
        rows.push({
          box: 'Balance - Side',
          student: studentSideOf(result.balance),
          correct: correctSide,
          isCorrect: result.balance.sideCorrect
        })
        rows.push({
          box: 'Balance - Label',
          student: blank(result.balance[labelField]),
          correct: blank(account.balance[labelField]),
          isCorrect: result.balance.labelCorrect
        })
        rows.push({
          box: 'Balance - Amount',
          student: blank(result.balance[correctSide]),
          correct: blank(account.balance[correctSide]),
          isCorrect: result.balance.amountCorrect
        })
      }

      return rows
    },
    async getAccountTitles () {
      try {
        const { data } = await axios.get('/api/questions/valid-accounting-journal-entries')
        this.accountTitles = data
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getEntryNarrative (entryIndex) {
      // Look up the narrative from the source entry using the student's selected entry index.
      // Fall back to the positional entry if nothing is selected yet.
      const studentEntry = this.studentEntries[entryIndex]
      const selectedIndex = studentEntry ? studentEntry.selectedEntryIndex : null
      const sourceIndex = selectedIndex !== null ? selectedIndex : entryIndex
      const sourceEntry = this.qtiJson.entries && this.qtiJson.entries[sourceIndex]
      return (sourceEntry && sourceEntry.entryNarrative) ? sourceEntry.entryNarrative.trim() : ''
    },
    getEntryOptionsFor (entryIndex) {
      const selectedByOthers = this.studentEntries
        .map((entry, idx) => idx === entryIndex ? null : entry.selectedEntryIndex)
        .filter(val => val !== null)
      const options = [{ value: null, text: 'Select an entry...' }]
      if (this.qtiJson.entries) {
        this.qtiJson.entries.forEach((entry, index) => {
          if (!selectedByOthers.includes(index)) {
            options.push({ value: index, text: this.escapeDollar(entry.entryText) })
          }
        })
      }
      return options
    },
    initializeStudentEntries () {
      if (!this.qtiJson.entries) return
      this.studentEntries = this.qtiJson.entries.map((entry) => {
        const numRows = entry.solutionRows ? entry.solutionRows.length : 2
        return {
          selectedEntryIndex: null,
          rows: Array(numRows).fill(null).map(() => ({
            accountTitle: '',
            debit: '',
            credit: ''
          }))
        }
      })
    },
    initializeStudentTAccounts () {
      if (!this.qtiJson.tAccounts) {
        this.studentTAccounts = []
        return
      }
      this.studentTAccounts = this.qtiJson.tAccounts.map((account) => ({
        rows: Array((account.postings || []).length).fill(null).map(() => ({
          debitLabel: '',
          debit: '',
          credit: '',
          creditLabel: ''
        })),
        balance: account.balance ? { debitLabel: '', debit: '', creditLabel: '', credit: '' } : null,
        beginningBalance: account.beginningBalance ? { debit: '', credit: '' } : null
      }))
    },
    loadStudentTAccountResponse () {
      let response = this.studentResponse || this.qtiJson.studentResponse
      if (typeof response === 'string') {
        try {
          response = JSON.parse(response)
        } catch (error) {
          return
        }
      }
      const tAccountsResponse = response && !Array.isArray(response) ? response.tAccounts : null
      if (!tAccountsResponse || !Array.isArray(tAccountsResponse)) return
      tAccountsResponse.forEach((responseAccount, accountIndex) => {
        if (!this.studentTAccounts[accountIndex]) return
        if (responseAccount.rows) {
          responseAccount.rows.forEach((row, rowIndex) => {
            if (this.studentTAccounts[accountIndex].rows[rowIndex]) {
              this.studentTAccounts[accountIndex].rows[rowIndex] = {
                debitLabel: row.debitLabel || '',
                debit: row.debit || '',
                credit: row.credit || '',
                creditLabel: row.creditLabel || ''
              }
            }
          })
        }
        if (responseAccount.balance && this.studentTAccounts[accountIndex].balance) {
          this.studentTAccounts[accountIndex].balance = {
            debitLabel: responseAccount.balance.debitLabel || '',
            debit: responseAccount.balance.debit || '',
            creditLabel: responseAccount.balance.creditLabel || '',
            credit: responseAccount.balance.credit || ''
          }
        }
        if (responseAccount.beginningBalance && this.studentTAccounts[accountIndex].beginningBalance) {
          this.studentTAccounts[accountIndex].beginningBalance = {
            debit: responseAccount.beginningBalance.debit || '',
            credit: responseAccount.beginningBalance.credit || ''
          }
        }
      })
    },
    // Mirrors the Journal Entries table's incomplete-field styling: while the
    // T-Accounts section as a whole is unfinished, blank fields render as
    // "needs attention" (matching platform styling) rather than plain/neutral.
    // For an untouched row, both amount cells AND both label pickers are flagged
    // (since the student hasn't indicated a side yet); once either amount is
    // filled, only that side's label is checked.
    isTAccountFieldIncomplete (accountIndex, rowIndex, field) {
      if (this.parsedTAccountGradingResults) return false
      if (!this.tAccountsIncomplete) return false
      const row = this.studentTAccounts[accountIndex] && this.studentTAccounts[accountIndex].rows[rowIndex]
      if (!row) return false
      const hasDebit = row.debit !== '' && row.debit !== null
      const hasCredit = row.credit !== '' && row.credit !== null

      if (field === 'debit' || field === 'credit') {
        return !hasDebit && !hasCredit
      }
      if (field === 'debitLabel') {
        if (!hasDebit && !hasCredit) return true
        return hasDebit && (!row.debitLabel || row.debitLabel.trim() === '')
      }
      if (field === 'creditLabel') {
        if (!hasDebit && !hasCredit) return true
        return hasCredit && (!row.creditLabel || row.creditLabel.trim() === '')
      }
      return false
    },
    getTAccountFieldClass (accountIndex, rowIndex, field) {
      if (this.hasStartedEditing) return ''
      const results = this.parsedTAccountGradingResults
      if (!results || !results[accountIndex] || !results[accountIndex].rows || !results[accountIndex].rows[rowIndex]) return ''
      const row = results[accountIndex].rows[rowIndex]
      // Debit and credit are graded independently now (a row may legitimately
      // hold both), so each field reads its own correctness flag directly
      // rather than inferring "whichever side was used."
      const fieldToResultKey = {
        debitLabel: 'debitLabelCorrect',
        debit: 'debitCorrect',
        creditLabel: 'creditLabelCorrect',
        credit: 'creditCorrect'
      }
      const resultKey = fieldToResultKey[field]
      const correct = resultKey ? row[resultKey] : undefined
      if (correct === undefined || correct === null) return ''
      return correct ? 'border-success' : 'border-danger'
    },
    getTAccountBalanceFieldClass (accountIndex, field) {
      if (this.hasStartedEditing) return ''
      const results = this.parsedTAccountGradingResults
      if (!results || !results[accountIndex] || !results[accountIndex].balance) return ''
      const balance = results[accountIndex].balance
      const fieldKey = field + 'Correct'
      if (balance[fieldKey] === undefined) return ''
      return balance[fieldKey] ? 'border-success' : 'border-danger'
    },
    // Mirrors isTAccountFieldIncomplete's logic for postings: an untouched row
    // flags both sides until either amount is filled, at which point only that
    // side's label is checked.
    isTAccountBalanceIncomplete (accountIndex, field) {
      if (this.parsedTAccountGradingResults) return false
      if (!this.tAccountsIncomplete) return false
      const balance = this.studentTAccounts[accountIndex] && this.studentTAccounts[accountIndex].balance
      if (!balance) return false
      const hasDebit = balance.debit !== '' && balance.debit !== null
      const hasCredit = balance.credit !== '' && balance.credit !== null

      if (field === 'debit' || field === 'credit') {
        return !hasDebit && !hasCredit
      }
      if (field === 'debitLabel') {
        if (!hasDebit && !hasCredit) return true
        return hasDebit && (!balance.debitLabel || balance.debitLabel.trim() === '')
      }
      if (field === 'creditLabel') {
        if (!hasDebit && !hasCredit) return true
        return hasCredit && (!balance.creditLabel || balance.creditLabel.trim() === '')
      }
      return false
    },
    getTAccountBeginningBalanceFieldClass (accountIndex, field) {
      if (this.hasStartedEditing) return ''
      const results = this.parsedTAccountGradingResults
      if (!results || !results[accountIndex] || !results[accountIndex].beginningBalance) return ''
      const beginningBalance = results[accountIndex].beginningBalance
      const fieldKey = field + 'Correct'
      if (beginningBalance[fieldKey] === undefined) return ''
      return beginningBalance[fieldKey] ? 'border-success' : 'border-danger'
    },
    isTAccountBeginningBalanceIncomplete (accountIndex, field) {
      if (this.parsedTAccountGradingResults) return false
      if (!this.tAccountsIncomplete) return false
      const beginningBalance = this.studentTAccounts[accountIndex] && this.studentTAccounts[accountIndex].beginningBalance
      if (!beginningBalance) return false
      const hasDebit = beginningBalance.debit !== '' && beginningBalance.debit !== null
      const hasCredit = beginningBalance.credit !== '' && beginningBalance.credit !== null
      return !hasDebit && !hasCredit
    },
    loadStudentResponse () {
      let response = this.studentResponse || this.qtiJson.studentResponse
      if (typeof response === 'string') {
        try {
          response = JSON.parse(response)
        } catch (error) {
          console.error('Error parsing studentResponse:', error)
          return
        }
      }
      if (!Array.isArray(response) && response && response.entries) {
        response = response.entries
      }
      if (response && Array.isArray(response)) {
        response.forEach((responseEntry, entryIndex) => {
          if (this.studentEntries[entryIndex]) {
            this.studentEntries[entryIndex].selectedEntryIndex = responseEntry.selectedEntryIndex ?? null
            if (responseEntry.rows) {
              responseEntry.rows.forEach((row, rowIndex) => {
                if (this.studentEntries[entryIndex].rows[rowIndex]) {
                  this.studentEntries[entryIndex].rows[rowIndex] = {
                    accountTitle: row.accountTitle || '',
                    debit: row.debit || '',
                    credit: row.credit || ''
                  }
                }
              })
            }
          }
        })
      }
    },
    isCreditRow (entryIndex, rowIndex) {
      // eslint-disable-next-line no-unused-expressions
      this.indentTracker
      const row = this.studentEntries[entryIndex]?.rows[rowIndex]
      if (!row) return false
      return row.credit && row.credit.trim() !== ''
    },
    onAmountInput (entryIndex, rowIndex, field) {
      this.clearFieldColor(entryIndex, rowIndex, field)
      this.indentTracker++
    },
    clearEntryColor (entryIndex) {
      this.hasStartedEditing = true
    },
    clearFieldColor (entryIndex, rowIndex, field) {
      this.hasStartedEditing = true
    },
    isIncomplete (entryIndex, rowIndex, field) {
      if (this.parsedGradingResults && this.parsedGradingResults.length > 0) return false
      if (!this.showValidationWarning) return false
      const entry = this.studentEntries[entryIndex]
      if (!entry) return false
      if (field === 'entry') return entry.selectedEntryIndex === null
      const row = entry.rows[rowIndex]
      if (!row) return false
      if (field === 'accountTitle') return !row.accountTitle || row.accountTitle.trim() === ''
      if (field === 'debit' || field === 'credit') {
        return (!row.debit || row.debit === '') && (!row.credit || row.credit === '')
      }
      return false
    },
    getStudentResponse () {
      if (this.showValidationWarning) return null
      if (this.qtiJson.includeTAccounts && this.tAccountsIncomplete) return null
      return {
        entries: this.studentEntries,
        tAccounts: this.studentTAccounts
      }
    },
    getEntryCellClass (entryIndex) {
      if (this.hasStartedEditing) return ''
      if (!this.parsedGradingResults ||
        !this.parsedGradingResults[entryIndex] ||
        this.parsedGradingResults[entryIndex].selectedEntryCorrect === undefined) {
        return ''
      }
      return this.parsedGradingResults[entryIndex].selectedEntryCorrect
        ? 'border-success'
        : 'border-danger'
    },
    getFieldClass (entryIndex, rowIndex, field) {
      if (this.hasStartedEditing) return ''
      if (!this.parsedGradingResults ||
        !this.parsedGradingResults[entryIndex] ||
        !this.parsedGradingResults[entryIndex].rows ||
        !this.parsedGradingResults[entryIndex].rows[rowIndex]) {
        return ''
      }
      const row = this.parsedGradingResults[entryIndex].rows[rowIndex]
      const fieldKey = field + 'Correct'
      if (row[fieldKey] === undefined) return ''
      return row[fieldKey] ? 'border-success' : 'border-danger'
    }
  }
}
</script>

<style scoped>
.instructions-section {
  background-color: #f8f9fa;
  padding: 1.5rem;
  border-radius: 0.25rem;
}

.section-divider {
  border: none;
  border-top: 3px solid #dee2e6;
  margin: 2rem 0;
}

.student-work-section {
  padding-top: 1rem;
}

.table-header {
  background-color: #f8f9fa;
  font-weight: 600;
}

.journal-entry-table {
  margin-bottom: 0;
}

.entry-divider {
  border-top: 2px solid #6c757d !important;
}

.entry-cell {
  vertical-align: top !important;
  padding-top: 0.5rem !important;
}

.account-indent {
  padding-left: 2rem !important;
}

.amount-input {
  text-align: right;
}

.entry-narrative {
  font-size: 0.82rem;
  color: #6c757d;
  font-style: italic;
  margin-top: 4px;
  padding-left: 2px;
}

select.border-success,
input.border-success {
  border: 2px solid #0d6832 !important;
  box-shadow: none !important;
}

select.border-danger,
input.border-danger {
  border: 2px solid #b02a37 !important;
  box-shadow: none !important;
}

select.is-incomplete,
input.is-incomplete {
  border: 2px solid #997404 !important;
  background-color: #fff9e6 !important;
}

.t-account-table {
  margin-bottom: 0;
}

.t-account-block {
  background-color: #fff;
  border: 1px solid #e2e5e9;
  border-radius: 0.5rem;
  padding: 1rem 1rem 1.25rem;
  margin-bottom: 1.5rem;
}

.t-account-title {
  text-align: center;
  font-size: 1.15rem;
  font-weight: 700;
  color: #2c3e63;
  padding-bottom: 0.4rem;
  border-bottom: 2px solid #dee2e6;
  margin-bottom: 0.6rem;
}

.t-account-table,
.t-account-table th,
.t-account-table td {
  border: none;
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

.t-account-debug-panel {
  border-top: 2px dashed #adb5bd;
  padding-top: 1rem;
}

.debug-table {
  font-size: 0.85rem;
}

.debug-table caption {
  color: #495057;
}
</style>
