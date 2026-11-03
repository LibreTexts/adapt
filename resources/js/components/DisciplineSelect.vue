<template>
  <div>
    <b-form-select v-model="disciplineId"
                   style="width:200px"
                   size="sm"
                   :options="disciplineOptions"
                   class="mt-2 mr-2"
                   @change="updateDiscipline"
    />
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'DisciplineSelect',
  props: {
    currentDisciplineId: {
      type: Number,
      default: null
    },
    courseId: {
      type: Number,
      default: 0
    },
    disciplineOptions: {
      type: Array,
      default: () => {
      }
    }
  },
  data: () => ({
    disciplineId: null
  }),
  watch: {
    currentDisciplineId: {
      immediate: true,
      handler (id) {
        this.disciplineId = id
      }
    }
  },
  methods: {
    async updateDiscipline () {
      try {
        const { data } = await axios.patch(`/api/courses/${this.courseId}/update-discipline`, { discipline_id: this.disciplineId })
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.$emit('reload')
    }
  }
}
</script>
