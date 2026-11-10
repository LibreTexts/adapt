<template>
  <div class="lms-status">
    <b-alert :show="true" variant="info">
      Every {{ cadenceMinutes }} minutes ADAPT asks each school's LMS for an LTI access token using ADAPT's own LTI
      key (the same first step as grade passback). For Brightspace, which shares one token server across schools, it
      also checks that the school's own site responds. Blackboard has one shared registration, so it appears as a
      single card. <strong>Down</strong> means the LMS didn't respond or returned a server error, even after a retry.
      <strong>Auth error</strong> means the LMS responded but rejected ADAPT's key, so that school's integration is
      broken. An email goes out whenever a school's status changes.
    </b-alert>

    <div class="d-flex flex-wrap align-items-center mb-3">
      <span v-if="latestCheckedAt" class="text-muted small mr-3">
        Last checked {{ relativeTime(latestCheckedAt) }} ({{ formatTime(latestCheckedAt) }})
      </span>
      <b-badge v-if="viewingPast" variant="warning" class="mr-2 p-2">
        Viewing check from {{ formatTime(viewingCheckedAt) }}
      </b-badge>
      <b-button v-if="viewingPast" size="sm" variant="outline-primary" class="mr-2" @click="loadStatus(null)">
        Back to latest
      </b-button>
      <b-button size="sm" variant="outline-secondary" :disabled="isLoading" @click="loadStatus(viewingPast ? viewingCheckedAt : null)">
        <b-spinner v-if="isLoading" small/>
        <span v-else>Refresh</span>
      </b-button>
    </div>

    <div v-if="loaded && !timeline.length" class="border rounded p-4 text-center text-muted">
      No LMS checks have been recorded yet. They run every {{ cadenceMinutes }} minutes in production, or run
      <code>php artisan check:lmsStatus</code> to check now.
    </div>

    <div v-if="timeline.length">
      <b-row class="mb-3">
        <b-col v-for="tile in summaryTiles" :key="tile.key" cols="6" md="3" class="mb-2">
          <div class="summary-tile border rounded p-3 h-100" :class="`summary-${tile.key}`">
            <div class="small text-muted">{{ tile.label }}</div>
            <div class="summary-value">{{ tile.value }}</div>
          </div>
        </b-col>
      </b-row>

      <div class="mb-3">
        <div class="d-flex flex-wrap justify-content-between small text-muted mb-1">
          <span>Last {{ retentionHours }} hours &mdash; click a bar to see that check</span>
          <span>
            <span class="legend-dot status-up"/> all up
            <span class="legend-dot status-auth_error ml-2"/> auth errors
            <span class="legend-dot status-down ml-2"/> something down
          </span>
        </div>
        <div class="timeline border rounded">
          <button v-for="run in timeline"
                  :key="run.checked_at"
                  type="button"
                  class="timeline-bar"
                  :class="[`status-${worstStatus(run)}`, { selected: run.checked_at === viewingCheckedAt }]"
                  :title="timelineTitle(run)"
                  :aria-label="timelineTitle(run)"
                  @click="loadStatus(run.checked_at === latestCheckedAt ? null : run.checked_at)"
          />
        </div>
        <div class="d-flex justify-content-between small text-muted mt-1">
          <span>{{ formatTime(timeline[0].checked_at) }}</span>
          <span>{{ formatTime(timeline[timeline.length - 1].checked_at) }}</span>
        </div>
      </div>

      <div class="d-flex flex-wrap align-items-center mb-3">
        <b-form-input v-model="search"
                      size="sm"
                      placeholder="Search schools or URLs"
                      class="mr-3 mb-2 search-input"
                      aria-label="Search schools"
        />
        <b-form-select v-model="lmsFilter"
                       size="sm"
                       :options="lmsOptions"
                       class="mr-3 mb-2 lms-select"
                       aria-label="Filter by LMS"
        />
        <b-button-group size="sm" class="mb-2">
          <b-button v-for="option in filterOptions"
                    :key="option.value"
                    :variant="filter === option.value ? 'primary' : 'outline-primary'"
                    @click="filter = option.value"
          >
            {{ option.text }} ({{ option.count }})
          </b-button>
        </b-button-group>
      </div>

      <b-row>
        <b-col v-for="school in filteredSchools" :key="school.id" cols="12" md="6" lg="4" class="mb-3">
          <div class="school-card border rounded p-3 h-100"
               :class="`border-${statusVariant(school.status)}`"
               role="button"
               tabindex="0"
               @click="openDetail(school)"
               @keydown.enter="openDetail(school)"
          >
            <div class="d-flex justify-content-between align-items-start">
              <div class="pr-2 school-name">
                <strong>{{ school.name }}</strong>
                <div class="small text-muted text-truncate">
                  {{ lmsLabel(school.lms) }}<span v-if="school.base_url"> &middot; {{ host(school.base_url) }}</span>
                </div>
              </div>
              <b-badge :variant="statusVariant(school.status)">{{ statusLabel(school.status) }}</b-badge>
            </div>
            <div class="d-flex justify-content-between small mt-2">
              <span>{{ school.response_ms !== null ? `${school.response_ms} ms` : '—' }}</span>
              <span>{{ school.uptime_pct !== null ? `${school.uptime_pct}% up (${retentionHours}h)` : '' }}</span>
            </div>
            <svg class="sparkline mt-1" viewBox="0 0 100 24" preserveAspectRatio="none" aria-hidden="true">
              <polyline v-if="sparklinePoints(school.sparkline)" :points="sparklinePoints(school.sparkline)" class="sparkline-line"/>
              <template v-for="(point, index) in school.sparkline">
                <rect v-if="point.status !== 'up'"
                      :key="index"
                      :x="index * (100 / Math.max(1, school.sparkline.length))"
                      y="20"
                      :width="100 / Math.max(1, school.sparkline.length)"
                      height="4"
                      :class="`fill-${point.status}`"
                />
              </template>
            </svg>
            <div v-if="school.error" class="small text-danger text-truncate" :title="school.error">{{ school.error }}</div>
          </div>
        </b-col>
      </b-row>
      <div v-if="!filteredSchools.length" class="text-muted text-center p-3">No schools match.</div>
    </div>

    <b-modal id="modal-lms-status-detail"
             :title="selected ? selected.name : ''"
             size="lg"
             hide-footer
             @hidden="selected = null; history = []"
    >
      <div v-if="selected">
        <b-row>
          <b-col md="6">
            <h6>Latest result</h6>
            <dl class="row small mb-3">
              <dt class="col-5">Status</dt>
              <dd class="col-7"><b-badge :variant="statusVariant(selected.status)">{{ statusLabel(selected.status) }}</b-badge></dd>
              <dt class="col-5">HTTP code</dt>
              <dd class="col-7">{{ selected.http_code || '—' }}</dd>
              <dt class="col-5">Response time</dt>
              <dd class="col-7">{{ selected.response_ms !== null ? `${selected.response_ms} ms` : '—' }}</dd>
              <dt v-if="selected.error" class="col-5">Error</dt>
              <dd v-if="selected.error" class="col-7 text-danger text-break">{{ selected.error }}</dd>
            </dl>
          </b-col>
          <b-col md="6">
            <h6>Instance</h6>
            <dl class="row small mb-3">
              <dt class="col-5">LMS</dt>
              <dd class="col-7">{{ lmsLabel(selected.lms) }}</dd>
              <dt class="col-5">Site</dt>
              <dd class="col-7 text-break">
                <a v-if="selected.base_url" :href="selected.base_url" target="_blank" rel="noopener">{{ selected.base_url }}</a>
                <span v-else-if="selected.lms === 'blackboard'">Shared by all Blackboard schools</span>
                <span v-else>—</span>
              </dd>
              <dt class="col-5">What's checked</dt>
              <dd class="col-7 text-break">
                <code>POST {{ selected.token_url || '—' }}</code>
                <span v-if="selected.site_check_url"><br><code>GET {{ selected.site_check_url }}</code></span>
              </dd>
              <dt class="col-5">Uptime ({{ retentionHours }}h)</dt>
              <dd class="col-7">{{ historyUptime }}</dd>
            </dl>
          </b-col>
        </b-row>

        <h6>Response time, last {{ retentionHours }} hours</h6>
        <div v-if="historyLoading" class="text-center p-3"><b-spinner small/></div>
        <div v-else-if="history.length">
          <div class="small text-muted">Peak: {{ historyMaxMs }} ms &middot; bottom strip shows each check's status</div>
          <svg class="history-chart border rounded" viewBox="0 0 600 140" preserveAspectRatio="none" role="img"
               :aria-label="`Response time chart for ${selected.name}`"
          >
            <polyline :points="historyPoints" class="history-line"/>
            <rect v-for="(check, index) in history"
                  :key="check.checked_at"
                  :x="index * (600 / history.length)"
                  y="126"
                  :width="Math.max(1, 600 / history.length)"
                  height="14"
                  :class="`fill-${check.status}`"
            >
              <title>{{ formatTime(check.checked_at) }}: {{ statusLabel(check.status) }}{{ check.response_ms !== null ? `, ${check.response_ms} ms` : '' }}</title>
            </rect>
          </svg>
          <div class="d-flex justify-content-between small text-muted">
            <span>{{ formatTime(history[0].checked_at) }}</span>
            <span>{{ formatTime(history[history.length - 1].checked_at) }}</span>
          </div>

          <h6 class="mt-3">Recent problems</h6>
          <p v-if="!recentProblems.length" class="small text-muted">None in the last {{ retentionHours }} hours.</p>
          <b-table v-else small striped :items="recentProblems" :fields="problemFields" class="small">
            <template v-slot:cell(checked_at)="data">{{ formatTime(data.item.checked_at) }}</template>
            <template v-slot:cell(status)="data">
              <b-badge :variant="statusVariant(data.item.status)">{{ statusLabel(data.item.status) }}</b-badge>
            </template>
          </b-table>
        </div>
      </div>
    </b-modal>
  </div>
</template>

<script>
import axios from 'axios'

const STATUS_LABELS = { up: 'Up', down: 'Down', auth_error: 'Auth error' }
const STATUS_VARIANTS = { up: 'success', down: 'danger', auth_error: 'warning' }

export default {
  name: 'LmsStatusMonitor',
  data: () => ({
    isLoading: false,
    loaded: false,
    retentionHours: 72,
    cadenceMinutes: 10,
    lmsLabels: {},
    timeline: [],
    latestCheckedAt: null,
    viewingCheckedAt: null,
    schools: [],
    summary: { total: 0, up: 0, down: 0, auth_error: 0, avg_response_ms: null },
    search: '',
    lmsFilter: 'all',
    filter: 'all',
    selected: null,
    history: [],
    historyLoading: false,
    refreshTimer: null,
    now: Date.now(),
    problemFields: [
      { key: 'checked_at', label: 'Time' },
      { key: 'status', label: 'Status' },
      { key: 'http_code', label: 'HTTP' },
      { key: 'error', label: 'Error' }
    ]
  }),
  computed: {
    viewingPast () {
      return this.viewingCheckedAt && this.viewingCheckedAt !== this.latestCheckedAt
    },
    summaryTiles () {
      return [
        { key: 'up', label: 'Up', value: `${this.summary.up} / ${this.summary.total}` },
        { key: 'down', label: 'Down', value: this.summary.down },
        { key: 'auth_error', label: 'Auth errors', value: this.summary.auth_error },
        {
          key: 'ms',
          label: 'Avg response',
          value: this.summary.avg_response_ms !== null ? `${this.summary.avg_response_ms} ms` : '—'
        }
      ]
    },
    lmsOptions () {
      const present = [...new Set(this.schools.map(school => school.lms))]
        .sort((a, b) => this.lmsLabel(a).localeCompare(this.lmsLabel(b)))
      return [{ value: 'all', text: `All LMSs (${this.schools.length})` }].concat(present.map(lms => ({
        value: lms,
        text: `${this.lmsLabel(lms)} (${this.schools.filter(school => school.lms === lms).length})`
      })))
    },
    schoolsForLms () {
      return this.lmsFilter === 'all' ? this.schools : this.schools.filter(school => school.lms === this.lmsFilter)
    },
    filterOptions () {
      const count = status => this.schoolsForLms.filter(school => school.status === status).length
      return [
        { value: 'all', text: 'All', count: this.schoolsForLms.length },
        { value: 'down', text: 'Down', count: count('down') },
        { value: 'auth_error', text: 'Auth error', count: count('auth_error') },
        { value: 'up', text: 'Up', count: count('up') }
      ]
    },
    filteredSchools () {
      const term = this.search.trim().toLowerCase()
      return this.schoolsForLms.filter(school =>
        (this.filter === 'all' || school.status === this.filter) &&
        (!term || `${school.name} ${school.base_url || ''} ${this.lmsLabel(school.lms)}`.toLowerCase().includes(term))
      )
    },
    historyMaxMs () {
      const values = this.history.map(check => check.response_ms).filter(ms => ms !== null)
      return values.length ? Math.max(...values) : 0
    },
    historyPoints () {
      return this.linePoints(this.history.map(check => check.response_ms), 600, 8, 112, this.historyMaxMs)
    },
    historyUptime () {
      if (!this.history.length) {
        return this.selected && this.selected.uptime_pct !== null ? `${this.selected.uptime_pct}%` : '—'
      }
      const up = this.history.filter(check => check.status === 'up').length
      return `${Math.round(1000 * up / this.history.length) / 10}% (${up} of ${this.history.length} checks)`
    },
    recentProblems () {
      return this.history.filter(check => check.status !== 'up').slice(-10).reverse()
    }
  },
  mounted () {
    this.loadStatus(null)
    // Keep the "latest" view current; leave a past check alone while someone is looking at it.
    this.refreshTimer = setInterval(() => {
      this.now = Date.now()
      if (!this.viewingPast && !this.isLoading) {
        this.loadStatus(null, true)
      }
    }, 60000)
  },
  beforeDestroy () {
    clearInterval(this.refreshTimer)
  },
  methods: {
    async loadStatus (at, quiet = false) {
      if (!quiet) {
        this.isLoading = true
      }
      try {
        const { data } = await axios.get('/api/lms-status', { params: at ? { at } : {} })
        if (data.type !== 'success') {
          if (!quiet) {
            this.$noty.error(data.message)
          }
          return
        }
        this.retentionHours = data.retention_hours
        this.cadenceMinutes = data.cadence_minutes
        this.lmsLabels = data.lms_labels || {}
        this.timeline = data.timeline
        this.latestCheckedAt = data.latest_checked_at
        this.viewingCheckedAt = data.viewing_checked_at
        this.schools = data.schools
        this.summary = data.summary
        this.now = Date.now()
        this.loaded = true
      } catch (error) {
        if (!quiet) {
          this.$noty.error(error.message)
        }
      } finally {
        this.isLoading = false
      }
    },
    async openDetail (school) {
      this.selected = school
      this.history = []
      this.historyLoading = true
      this.$bvModal.show('modal-lms-status-detail')
      try {
        const { data } = await axios.get(`/api/lms-status/${school.id}/history`)
        if (data.type === 'success') {
          this.history = data.history
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.historyLoading = false
      }
    },
    lmsLabel (lms) {
      return this.lmsLabels[lms] || lms
    },
    worstStatus (run) {
      if (run.down) {
        return 'down'
      }
      return run.auth_error ? 'auth_error' : 'up'
    },
    timelineTitle (run) {
      return `${this.formatTime(run.checked_at)}: ${run.up} up, ${run.down} down, ${run.auth_error} auth errors`
    },
    statusLabel (status) {
      return STATUS_LABELS[status] || status
    },
    statusVariant (status) {
      return STATUS_VARIANTS[status] || 'secondary'
    },
    host (url) {
      if (!url) {
        return ''
      }
      try {
        return new URL(url).host
      } catch (e) {
        return url
      }
    },
    formatTime (iso) {
      return iso ? new Date(iso).toLocaleString([], {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
      }) : ''
    },
    relativeTime (iso) {
      const minutes = Math.round((this.now - new Date(iso).getTime()) / 60000)
      if (minutes < 1) {
        return 'just now'
      }
      if (minutes < 60) {
        return `${minutes} min ago`
      }
      const hours = Math.round(minutes / 60)
      return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`
    },
    sparklinePoints (sparkline) {
      const values = sparkline.map(point => point.ms)
      const max = Math.max(0, ...values.filter(ms => ms !== null))
      return this.linePoints(values, 100, 2, 18, max)
    },
    /**
     * Polyline points for a series of ms values (nulls are skipped), scaled into [top, bottom] of a box `width` wide.
     */
    linePoints (values, width, top, bottom, max) {
      if (!max || values.length < 2) {
        return ''
      }
      const step = width / (values.length - 1)
      return values
        .map((ms, index) => ms === null ? null : `${(index * step).toFixed(1)},${(bottom - (ms / max) * (bottom - top)).toFixed(1)}`)
        .filter(point => point !== null)
        .join(' ')
    }
  }
}
</script>

<style scoped>
.summary-value {
  font-size: 1.6rem;
  font-weight: 600;
}

.summary-up .summary-value { color: #1e7e34; }
.summary-down .summary-value { color: #c82333; }
.summary-auth_error .summary-value { color: #9a6b00; }

.timeline {
  display: flex;
  align-items: stretch;
  height: 36px;
  padding: 3px;
  gap: 1px;
  overflow: hidden;
}

.timeline-bar {
  flex: 1 1 0;
  min-width: 2px;
  border: 0;
  padding: 0;
  border-radius: 1px;
  cursor: pointer;
  opacity: 0.75;
}

.timeline-bar:hover,
.timeline-bar:focus,
.timeline-bar.selected {
  opacity: 1;
  outline: 2px solid #343a40;
  outline-offset: 0;
}

.status-up { background-color: #28a745; }
.status-down { background-color: #dc3545; }
.status-auth_error { background-color: #ffc107; }

.legend-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 2px;
  vertical-align: middle;
}

.search-input {
  max-width: 280px;
}

.lms-select {
  max-width: 200px;
}

.school-card {
  cursor: pointer;
  border-width: 2px !important;
  transition: box-shadow 0.15s;
}

.school-card:hover,
.school-card:focus {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
  outline: none;
}

.school-name {
  min-width: 0;
}

.sparkline {
  width: 100%;
  height: 24px;
  display: block;
}

.sparkline-line,
.history-line {
  fill: none;
  stroke: #007bff;
  stroke-width: 1.5;
  vector-effect: non-scaling-stroke;
}

.fill-up { fill: #28a745; }
.fill-down { fill: #dc3545; }
.fill-auth_error { fill: #ffc107; }

.history-chart {
  width: 100%;
  height: 160px;
  display: block;
}
</style>
