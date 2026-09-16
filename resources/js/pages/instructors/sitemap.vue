<template>
  <div>
    <PageTitle title="Sitemap"/>
    <b-modal
      id="modal-course-assignments"
      :title="`${course.text} assignments`"
      size="xl"
      :hide-footer="true"
    >
      <ol>
        <li v-for="assignment in courseAssignments" :key="`course-${course.value}-${assignment.value}`" class="pb-2">
          {{ assignment.text }}:
          <ul>
            <li>
              Question Level:
              <router-link :to="{name: 'instructors.assignments.questions', params: {assignmentId: assignment.value}}">
                Summary -
              </router-link>
              <router-link :to="{name: 'questions.view', params: {assignmentId: assignment.value}}">
                Individual -
              </router-link>
              <router-link :to="getQuestionsRouterLink(assignment)">
                Get Questions
              </router-link>
            </li>
            <li>
              Assignment Level:
              <router-link :to="{name: 'instructors.assignments.summary', params: {assignmentId: assignment.value}}">
                Summary
              </router-link>
              -
              <router-link :to="{name: 'instructors.assignments.properties', params: {assignmentId: assignment.value}}">
                Properties
              </router-link>
              -
              <router-link
                :to="{name: 'instructors.assignments.control_panel', params: {assignmentId: assignment.value}}"
              >
                Control Panel
              </router-link>
              -
              <router-link
                :to="{name: 'instructors.assignments.overrides', params: {assignmentId: assignment.value}}"
              >
                Submission Overrides
              </router-link>
              -
              <router-link
                :to="{name: 'instructors.assignments.grader_access', params: {assignmentId: assignment.value}}"
              >
                Grader Access
              </router-link>
              -
              <router-link :to="{name: 'instructors.assignments.statistics', params: {assignmentId: assignment.value}}">
                Statistics
              </router-link>
              -
              <router-link :to="{name: 'instructors.assignments.gradebook', params: {assignmentId: assignment.value}}">
                Gradebook
              </router-link>
              -
              <router-link :to="{name: 'assignment.grading.index', params: {assignmentId: assignment.value}}">
                Grading
              </router-link>
            </li>
          </ul>
        </li>
      </ol>
    </b-modal>
    <div class="accordion" role="tablist">
      <b-card no-body class="mb-1">
        <b-card-header header-tag="header" class="p-1" role="tab">
          <b-button block v-b-toggle.accordion-1 variant="info">General Info</b-button>
        </b-card-header>
        <b-collapse id="accordion-1" visible accordion="my-accordion" role="tabpanel">
          <b-card-body>
            <b-card-text>
              <ul>
                <li>
                  <router-link :to="{path: '/'}">
                    Homepage
                  </router-link>
                </li>
                <li>
                  <router-link :to="{name: 'commons'}">
                    Commons
                  </router-link>
                </li>
                <li>
                  <a href="" @click.prevent="updateLibreOneProfile">
                    Settings - Profile
                  </a>
                </li>
                <li>
                  <a href="" @click.prevent="updateLibreOnePassword">
                    Settings - Password
                  </a>
                </li>
              </ul>
            </b-card-text>
          </b-card-body>
        </b-collapse>
      </b-card>
      <b-card no-body class="mb-1" v-if="courses.length">
        <b-card-header header-tag="header" class="p-1" role="tab">
          <b-button block v-b-toggle.accordion-2 variant="info">My Courses</b-button>
        </b-card-header>
        <b-collapse id="accordion-2" accordion="my-accordion" role="tabpanel">
          <b-card-body>
            <b-card-text>
          <span v-if="courses.length">
          You can directly view all of <router-link :to="{name: 'instructors.courses.index'}">your courses</router-link>
            or drill down to the course-assignment details below.
        </span>
              <ol>
                <li v-for="course in courses" :key="`course-${course.value}`">
                  <a href="" @click.prevent="openCourseModal(course)">{{ course.text }}</a>
                  <ul>
                    <li>
                      <router-link :to="{name: 'instructors.assignments.index', params: {courseId: course.value}}">
                        Assignments
                      </router-link>
                    </li>
                    <li>
                      <router-link :to="{name: 'gradebook.index', params: {courseId: course.value}}">
                        Gradebook
                      </router-link>
                    </li>
                    <li>
                      <router-link :to="{name: 'course_properties.general_info', params: {courseId: course.value}}">
                        Properties
                      </router-link>
                    </li>
                    <li>
                      <router-link :to="{name: 'course_properties.sections', params: {courseId: course.value}}">
                        Sections
                      </router-link>
                    </li>
                    <li>
                      <router-link :to="{name: 'course_properties.tethered_courses', params: {courseId: course.value}}">
                        Tethered Courses
                      </router-link>
                    </li>
                    <li>
                      <router-link :to="{name: 'course_properties.letter_grades', params: {courseId: course.value}}">
                        Grades
                      </router-link>
                    </li>
                    <li>
                      <router-link
                        :to="{name: 'course_properties.ungraded_submissions', params: {courseId: course.value}}"
                      >
                        Ungraded Submissions
                      </router-link>
                    </li>
                    <li>
                      <router-link
                        :to="{name: 'course_properties.graders', params: {courseId: course.value}}"
                      >
                        Graders
                      </router-link>
                    </li>
                    <li>
                      <router-link :to="{name: 'course_properties.students', params: {courseId: course.value}}">
                        Students
                      </router-link>
                    </li>
                    <li>
                      <router-link
                        :to="{name: 'course_properties.iframe_properties', params: {courseId: course.value}}"
                      >
                        Embed Properties
                      </router-link>
                    </li>
                    <li>
                      <router-link
                        :to="{name: 'gradebook.index', params: {courseId: course.value}}"
                      >
                        Gradebook
                      </router-link>
                    </li>
                  </ul>
                </li>
              </ol>
            </b-card-text>
          </b-card-body>
        </b-collapse>
      </b-card>
      <b-card no-body class="mb-1">
        <b-card-header header-tag="header" class="p-1" role="tab">
          <b-button block v-b-toggle.accordion-3 variant="info">Learning Trees</b-button>
        </b-card-header>
        <b-collapse id="accordion-3" accordion="my-accordion" role="tabpanel">
          <b-card-body>
            <b-card-text>
              <ul>
                <li>
                  <router-link
                    :to="{name: 'instructors.learning_trees.index'}"
                  >
                    Learning Trees Editor
                  </router-link>
                </li>
                <li>
                  <a href="/instructors/learning-trees/editor/0">Create New Learning Tree</a>
                </li>
              </ul>
              <span v-if="learningTrees.length">
          You can directly view and edit your learning trees using the following links.
        </span>
              <ol>
                <li v-for="learningTree in learningTrees" :key="`learning-tree-${learningTree.id}`">
                  <a :href="`/instructors/learning-trees/editor/${learningTree.id}`">{{ learningTree.title }}</a>
                </li>
              </ol>

            </b-card-text>
          </b-card-body>
        </b-collapse>
      </b-card>
      <b-card no-body class="mb-1">
        <b-card-header header-tag="header" class="p-1" role="tab">
          <b-button block v-b-toggle.accordion-4 variant="info">Questions</b-button>
        </b-card-header>
        <b-collapse id="accordion-4" accordion="my-accordion" role="tabpanel">
          <b-card-body>
            <b-card-text>
              <ul>
                <li>
                  <router-link :to="{name: 'question.editor'}">
                    Question Editor
                  </router-link>
                </li>
                <li>
                  <router-link :to="{name: 'question.editor', params:{tab: 'my-questions'}}">
                    My Questions
                  </router-link>
                </li>
              </ul>
            </b-card-text>
          </b-card-body>
        </b-collapse>
      </b-card>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { updateLibreOneProfile, updateLibreOnePassword } from '~/helpers/LibreOne'
export default {
  metaInfo () {
    return { title: 'Sitemap' }
  },
  data: () => ({
    courses: [],
    course: {},
    courseAssignments: [],
    learningTrees: []
  }),
  mounted () {
    this.getCoursesAndAssignments()
    this.getLearningTrees()
  },
  methods: {
    updateLibreOneProfile,
    updateLibreOnePassword,
    getQuestionsRouterLink (assignment) {
      return {
        name: assignment.assessment_type === 'learning tree' ? 'learning_trees.get' : 'questions.get',
        params: { assignmentId: assignment.value }
      }
    },
    openCourseModal (course) {
      this.course = course
      this.courseAssignments = this.assignments[course.value]
      this.$bvModal.show('modal-course-assignments')
    },
    async getCoursesAndAssignments () {
      const { data } = await axios.get('/api/courses/assignments')
      if (data.type === 'error') {
        this.$noty.error(data.message)
        return false
      }
      this.courses = data.courses
      this.assignments = data.assignments
    },
    async getLearningTrees () {
      try {
        const { data } = await axios.get('/api/learning-trees')
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.learningTrees = data.learning_trees
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>

<style scoped>

</style>
