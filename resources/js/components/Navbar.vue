<template>
  <div>
    <div v-show="false" id="support-widget-container"
         style="position: fixed; z-index: 9999; display: inline-flex; bottom: 0; right: 5px;"
    />
    <b-modal id="modal-switched-account"
             title="Success!"
             @hidden="loadCoursesPage"
    >
      <p>You are now logged in as <strong>{{ accountToSwitchTo.email }}</strong>.</p>
      <template #modal-footer>
        <b-button
          size="sm"
          variant="primary"
          class="float-right"
          @click="$bvModal.hide('modal-switched-account')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <Email id="contact-us-modal" ref="email"
           extra-email-modal-text="Please use this form to contact us regarding general questions or issues.  If you have a course specific question, please contact your instructor using your own email client."
           :from-user="user" title="Contact Us" type="contact_us" subject="General Inquiry"
    />
    <div v-if="showNavBar" id="navbar">
      <b-modal id="modal-switch-account"
               title="Switch Account"
      >
        {{ linkedAccounts }}
      </b-modal>
      <b-navbar toggleable="lg" class="pt-0 pb-0">
        <LibreOne size="sm" class="m-1"/>

        <b-navbar-brand>
          <a :href="getLogoHref()"><img src="https://cdn.libretexts.net/Logos/adapt_no_padding.png"
                                        :alt="user !== null && [2, 3, 4, 5].includes(user.role)
                                          ? 'ADAPT logo with redirect to My Courses' : 'ADAPT logo with redirect to main page'"
                                        @load="logoLoaded = true"
          >
          </a>
        </b-navbar-brand>

        <b-navbar-toggle target="nav-collapse" right/>

        <b-collapse id="nav-collapse" is-nav>
          <b-navbar-nav class="ml-auto">
            <b-nav-item v-show="!isAnonymousUser && !(user && (user.fake_student || user.testing_student))"
                        @click="contactUsWidget()"
            >
              <div class="mt-1"
              >Support
              </div>
            </b-nav-item>
            <b-nav-item v-if="user && (user.fake_student || user.testing_student || user.formative_student)"
                        @click.prevent="logout"
            >
              <span v-if="!user.formative_student">Logout</span>
              <span v-if="user.formative_student">End Session</span>
            </b-nav-item>
            <b-nav-item v-if="!user && !isLocal">
              <b-button
                size="sm"
                variant="primary"
                class="btn-rounded d-flex align-items-center"
                @click="beginLogin"
              >
                <i class="fas fa-user me-2"></i> Log In/Register
              </b-button>
            </b-nav-item>
            <b-nav-item v-if="!user && isLocal" @click="$router.push({ name: 'login' })">
              <span :style="this.$router.history.current.name === 'login' ? 'color:#6C757D' : ''">Log In</span>
            </b-nav-item>
            <b-nav-item v-show="!user && isLocal" right>
              <span @click="registerWithLibreOne">Register</span>
            </b-nav-item>
          </b-navbar-nav>
        </b-collapse>
        <div v-if="logoLoaded
          && user
          && ((![2,3,5].includes(user.role) && user.logged_in_as_user) || (user.role === 3 && user.is_instructor_logged_in_as_student && !user.fake_student))"
        >
          <b-button size="sm" variant="outline-danger" @click="exitLoginAs">
            Exit Login As
          </b-button>
        </div>
        <div v-if="logoLoaded && showToggleStudentView || (user && user.role === 5)" class="float-right">
          <toggle-button
            v-if="showToggleStudentView && (user !== null) && toggleInstructorStudentViewRouteNames.includes($route.name)"
            tabindex="0" class="mt-2" :width="140" :value="isInstructorView" :sync="true" :font-size="14" :margin="4"
            :color="toggleColors" :labels="{ checked: 'Instructor View', unchecked: 'Student View' }"
            :aria-label="isInstructorView ? 'Instructor view shown' : 'Student view shown'"
            @change="toggleStudentView()"
          />
          <span v-if="user && (!user.logged_in_as_user && user.id === 7665 && !isAdmin)">
            <router-link :to="{ name: 'loginAsSingle' }">
              <b-button size="sm" variant="outline-primary">Login As</b-button>
            </router-link>
          </span>
          <span v-if="user && user.logged_in_as_user">
            <b-button size="sm" variant="outline-danger" @click="exitLoginAs">Exit Login As</b-button>
          </span>
          <span
            v-if="isAdmin && (user !== null) && (!user.logged_in_as_user) && !user.fake_student"
          >
            <router-link :to="{ name: 'login.as' }">
              <b-button size="sm" variant="outline-primary">Control Panel</b-button>
            </router-link>
          </span>
          <span v-if="user && [2, 4, 5].includes(user.role)">
           <b-dropdown
             id="dropdown-right"
             :left="isPhone()"
             :right="!isPhone()"
             text="Dashboard"
             variant="primary"
             class="m-2"
             size="sm"
           >
              <b-dropdown-item v-for="location in dashboards" :key="location.text" href="#" :class="{
                'border-bottom': location.text === 'My Rubric Templates',
                'pb-2': location.text === 'My Rubric Templates',
                'pt-2': location.text === 'Search Questions'
              }" @click="loadRoutePath(location.routePath)"
              >
                <span class="hover-underline">{{ location.text }}</span>
              </b-dropdown-item>
            </b-dropdown>
          </span>
        </div>
      </b-navbar>
    </div>

    <b-nav v-if="logoLoaded" role="navigation" aria-label="breadcrumb" class="breadcrumb d-flex justify-content-between"
           style="padding-top:.3em !important;padding-bottom:0 !important; margin-bottom:0 !important;"
    >
      <span v-show="isCoInstructor" class="pt-1">
      <b-tooltip target="coInstructor-tooltip"
                 delay="500"
      >
        You are a co-instructor in this course. The main instructor is {{ mainInstructorName }}.
      </b-tooltip>
      <font-awesome-icon :icon="coInstructorIcon"
                         id="coInstructor-tooltip"
                         class="text-muted"
      />
        </span>
      <span v-if="(user === null) || (oneBreadcrumb && (user !== null))"
            style="padding-top:.3em;padding-bottom:0 !important; margin-bottom:0 !important; padding-left:16px"
      ><a
        v-if="breadcrumbs[0] && breadcrumbs[0]['text']" :href="breadcrumbs && breadcrumbs[0]['href']"
      >
        {{ breadcrumbs[0]['text'] }}
      </a></span>
      <b-breadcrumb v-if="!oneBreadcrumb && breadcrumbs[0] && breadcrumbs[0]['text'] && !(user && user.testing_student)"
                    :items="breadcrumbs"
                    style="padding-bottom:0 !important; margin-bottom:0 !important"
                    :style="breadcrumbs.length === 1 && breadcrumbs[0].active ?
                      'padding-top:.3em;padding-bottom:.3em !important; margin-bottom:0 !important' : 'padding-top:.3em;padding-bottom:0 !important; margin-bottom:0 !important'"
      />
      <b-navbar-nav class="ml-auto mt-0 mb-0 d-flex flex-row">
        <b-nav-item-dropdown
          v-if="user && !user.fake_student && !user.testing_student && !isLearningTreesEditor && !user.formative_student"
          right class="mr-2"
        >
          <!-- Using 'button-content' slot -->
          <template v-slot:button-content>
            <span class="hover-underline">Hi, {{ user.first_name }}!</span>
          </template>

          <b-dropdown-item v-if="!isAnonymousUser && !user.formative_student && localEnvironment"
                           @click="$router.push({ name: 'settings.profile' })"
          >
            <fa icon="cog" fixed-width/>
            <span class="hover-underline pl-3">{{ $t('settings') }}</span>
          </b-dropdown-item>

          <b-dropdown-item v-if="!isAnonymousUser && !user.formative_student && !localEnvironment"
                           @click="updateLibreOneProfile()"
          >
            <fa icon="user" fixed-width/>
            <span class="hover-underline pl-3">Profile</span>
          </b-dropdown-item>
          <b-dropdown-item v-if="!isAnonymousUser && !user.formative_student && !localEnvironment"
                           @click="updateLibreOnePassword()"
          >
            <fa icon="lock" fixed-width/>
            <span class="hover-underline pl-3">Reset Password</span>
          </b-dropdown-item>
          <b-dropdown-item v-if="user.role === 2"
                           @click="$router.push({ name: 'linked_accounts' })"
          >
            <fa icon="user-plus" fixed-width/>
            <span class="hover-underline pl-3">Linked Accounts</span>
          </b-dropdown-item>
          <b-dropdown-item v-if="!isAnonymousUser && !user.formative_student && user.role === 3 && !localEnvironment"
                           @click="$router.push({ name: 'notifications' })"
          >
            <fa icon="bell" fixed-width/>
            <span class="hover-underline pl-3">Notifications</span>
          </b-dropdown-item>


          <b-dropdown-item @click.prevent="logout">
            <fa icon="sign-out-alt" fixed-width/>
            <span class="hover-underline pl-3">{{ $t('logout') }}</span>
          </b-dropdown-item>
        </b-nav-item-dropdown>
      </b-navbar-nav>
      <b-nav-item-dropdown v-if="user && user.role === 2 && linkedAccounts.length" right>
        <template v-slot:button-content>
          <span v-b-tooltip.hover="{ delay: { show: 500, hide: 0 } }"
                :title="`You are currently logged in with the email address: ${user.email}`"
          >
            <b-icon-person-circle/>
          </span>
        </template>

        <!-- Dropdown listing linked accounts -->
        <b-dropdown-item
          v-for="linkedAccount in linkedAccounts"
          :key="`linked-account-${linkedAccount.id}`"
          :disabled="linkedAccount.id === user.id"
          @click="switchAccount(linkedAccount)"
        >
          <!-- Conditionally show the current account as muted -->
          <span :class="linkedAccount.id === user.id ? 'text-muted' : ''">
            {{ linkedAccount.email }}
            <span v-if="linkedAccount.id === user.id">(Current)</span> <!-- Label for current account -->
          </span>
        </b-dropdown-item>
      </b-nav-item-dropdown>
    </b-nav>
  </div>
</template>

<script>

import axios from 'axios'
import { mapGetters } from 'vuex'
import Email from './Email'
import { logout } from '~/helpers/Logout'
import { ToggleButton } from 'vue-js-toggle-button'
import { toggleInstructorStudentViewRouteNames } from '~/helpers/StudentInstructorViewToggles'
import { faUsers } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import LibreOne from './LibreOne.vue'
import { updateLibreOnePassword, updateLibreOneProfile } from '../helpers/LibreOne'
import { isPhone } from '../helpers/isPhone'

export default {
  components: {
    LibreOne,
    Email,
    FontAwesomeIcon,
    ToggleButton
  },
  props: {
    linkedAccounts: {
      type: Array,
      default: () => {
      }
    }
  },
  data: () => ({
    mainInstructorName: '',
    isCoInstructor: false,
    coInstructorIcon: faUsers,
    localEnvironment: false,
    accountToSwitchTo: {},
    toggleInstructorStudentViewRouteNames: toggleInstructorStudentViewRouteNames,
    toggleColors: window.config.toggleColors,
    environment: window.config.environment,
    isAnonymousUser: false,
    showNavBar: true,
    isLearningTreeView: true,
    isInstructorsMyCoursesView: true,
    showToggleStudentView: false,
    originalRole: 0,
    isInstructorView: false,
    canToggleStudentView: false,
    courseId: 0,
    assignmentId: 0,
    appName: window.config.appName,
    oneBreadcrumb: false,
    logoLoaded: false,
    isLearningTreesEditor: false,
    breadcrumbsLoaded: false,
    breadcrumbs: [
      {
        text: '',
        href: '#',
        active: true
      }
    ]
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    isAdmin: () => window.config.isAdmin,
    currentRouteName () {
      return this.$route.name
    },
    isLocal: () => window.config.environment === 'local',
    dashboards () {
      if (!this.user) {
        return []
      }
      let dashboards = [{ routePath: '/instructors/courses', text: 'My Courses' },
        { routePath: '/question-editor/my-questions', text: 'My Questions' }]
      if (this.user.id === 6314) {
        dashboards.unshift({ routePath: '/instructors/nursing-analytics', text: 'Analytics' })
      }
      if (this.user.role === 5) {
        dashboards.push({ routePath: '/all-questions/get', text: 'Non-instructor Editor Questions' })
      }
      if (this.user.role === 2) {
        let instructorDashboards = [
          { routePath: '/instructors/learning-trees', text: 'My Learning Trees' },
          { routePath: '/instructors/assignment-templates', text: 'My Assignment Templates' },
          { routePath: '/instructors/rubric-templates', text: 'My Rubric Templates' },
          { routePath: '/all-questions/get', text: 'Search Questions' },
          { routePath: '/all-learning-trees/get', text: 'Browse Learning Trees' },
          { routePath: '/open-courses/public', text: 'Public Courses' },
          { routePath: '/open-courses/commons', text: 'Commons' },
          { routePath: '/instructors/frameworks', text: 'Frameworks' }]

        dashboards = [...dashboards, ...instructorDashboards]
      }
      if (!this.isAdmin) {
        dashboards = dashboards.filter(dashboard => dashboard.routeName !== 'question.editor')
      }
      return dashboards
    }
  },
  watch: {
    '$route' (to, from) {
      if (to.params.courseId) {
        this.courseId = to.params.courseId
      }
      if (to.params.assignmentId) {
        this.assignmentId = to.params.assignmentId
      }
      this.canToggleStudentView = Boolean(this.assignmentId + this.courseId)
      this.isInstructorsMyCoursesView = to.name === 'instructors.courses.index'
      this.isLearningTreesEditor = (to.name === 'instructors.learning_trees.editor') ? 'color:#E9ECEF !important;' : ''
      this.showNavBar = !['init_lms_assignment', 'anonymous-users-entry'].includes(this.$router.history.current.name)
      if (this.showNavBar) {
        this.getBreadcrumbs(this.$router.history.current)
        this.breadcrumbsLoaded = true
      }
      this.showToggleStudentView = this.user !== null && (this.user.role === 2 || this.user.fake_student)
      this.isInstructorView = this.user !== null && this.user.role === 2
      this.isAnonymousUser = this.user !== null && this.user.email === 'anonymous'
    }
  },
  created () {
    this.logout = logout
    const widgetScript = document.createElement('script')
    widgetScript.setAttribute(
      'src',
      'https://cdn.libretexts.net/libretexts-support-widget.min.js'
    )
    document.head.appendChild(widgetScript)
  },
  mounted () {
    this.localEnvironment = window.config.environment === 'local'
  },
  methods: {
    isPhone,
    updateLibreOnePassword,
    updateLibreOneProfile,
    beginLogin () {
      window.location.href = '/api/oidc/initiate-login/web'
    },
    registerWithLibreOne () {
      window.location.href = this.environment === 'production'
        ? 'https://one.libretexts.org/register?source=adapt-registration'
        : 'https://staging.one.libretexts.org/register?source=adapt-registration'
    },
    loadCoursesPage () {
      window.location.replace('/instructors/courses')
    },
    async switchAccount (accountToSwitchTo) {
      this.accountToSwitchTo = accountToSwitchTo
      try {
        const { data } = await axios.patch(`/api/linked-account/switch/${accountToSwitchTo.id}`)
        if (data.type === 'success') {
          await this.$store.dispatch('auth/saveToken', {
            token: data.token,
            remember: this.remember
          })
          await this.$store.dispatch('auth/fetchUser')
          // Redirect to the correct home page
          Object.keys(localStorage).forEach((key) => {
            if (key !== ('appversion')) {
              delete localStorage[key]
            }
          })
          this.$bvModal.show('modal-switched-account')
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    contactUsWidget () {
      document.getElementById('supportButton').click()
    },
    async exitLoginAs () {
      try {
        const { data } = await axios.post('/api/user/exit-login-as')
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        } else {
          // Save the token.
          await this.$store.dispatch('auth/saveToken', {
            token: data.token,
            remember: false
          })
          // Fetch the user.
          await this.$store.dispatch('auth/fetchUser')
          if (this.$route.name !== 'instructors.courses.index') {
            await this.$router.push({ name: 'instructors.courses.index' }).then(() => {
              window.location.reload()
            })
          } else {
            location.reload()
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    loadRoutePath (routePath) {
      // need a reload since Search Questions is the same page as the Question Bank except for a parameter change
      window.location = routePath
    },
    getLogoHref () {
      let href = '/'
      if (this.user !== null) {
        if ([3, 4].includes(this.user.role)) {
          href = '/students/courses'
        }
        if ([2, 5].includes(this.user.role)) {
          href = '/instructors/courses'
        }
      }
      return href
    },
    async toggleStudentView () {
      if (!this.canToggleStudentView) {
        let message = 'Please visit a page within a course to toggle the view.'
        this.$noty.info(message)
        return false
      }
      try {
        const { data } = await axios.post('/api/user/toggle-student-view',
          {
            course_id: this.courseId,
            assignment_id: this.assignmentId,
            route_name: this.$router.history.current.name
          })
        console.log(data)
        if (data.type === 'success') {
          // Save the token.
          await this.$store.dispatch('auth/saveToken', {
            token: data.token,
            remember: false
          })
          await this.$store.dispatch('auth/fetchUser')
          this.isInstructorView = !this.isInstructorView
          if (data.new_route_name !== this.$router.history.current.name) {
            await this.getBreadcrumbs(this.$router)
            await this.$router.push({ name: data.new_route_name, params: { assignmentId: this.assignmentId } })
          } else {
            this.$router.go()
          }

          // Redirect to the correct home page
        } else {
          this.$noty.error(data.message)// no access
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getBreadcrumbs (router) {
      try {
        console.log(router.name)
        console.log({ 'name': router.name, 'params': router.params })
        this.breadcrumbs = [
          {
            text: '',
            href: '#',
            active: true
          }
        ]
        if (this.user && !localStorage.launchInNewWindow) {
          const { data } = await axios.post('/api/breadcrumbs', { 'name': router.name, 'params': router.params })
          if (data.type === 'success') {
            this.breadcrumbs = data.breadcrumbs
            this.isCoInstructor = data.is_co_instructor
            this.mainInstructorName = data.main_instructor_name
          }
        }
        this.oneBreadcrumb = this.breadcrumbs.length === 1 &&
          [
            'commons',
            'welcome',
            'refresh.question.requests',
            'manual.grade.passbacks',
            'testers.students.results'
          ].includes(router.name)
      } catch (error) {
        if (!error.message.includes('status code 401')) {
          this.$noty.error(error.message)
        }
      }
    },
    openSendEmailModal () {
      this.$refs.email.openSendEmailModal()
    }
  }
}
</script>

<style scoped>
.nav-link {
  padding-top: .25em;
}

.btn-rounded {
  border-radius: 6px; /* Rounded corners for modern look */
  padding: 6px; /* Adequate spacing */
}

.navbar-float-right {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 0.5rem;
}
</style>
