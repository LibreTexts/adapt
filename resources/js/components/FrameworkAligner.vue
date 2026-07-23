<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" modal-id="modal-level-form-errors" />
    <AllFormErrors :all-form-errors="allFormErrors" modal-id="modal-descriptors-form-errors" />
    <b-modal id="modal-confirm-delete-framework-levels-and-descriptors"
             title="Delete Framework Levels and Descriptors"
    >
      <p>
        You are about to delete all of the framework levels and associated
        descriptors. Any questions currently aligned with this framework will have their associated meta-tags removed.
      </p>
      <p>
        This action cannot be undone.
      </p>
      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="$bvModal.hide('modal-confirm-delete-framework-levels-and-descriptors')">
          Cancel
        </b-button>
        <b-button size="sm" variant="danger" @click="handleDeleteFrameworkLevelsAndDescriptors()">
          Delete
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-delete-descriptor"
             :title="`Confirm delete ${descriptorToDelete.descriptor}`"
    >
      <div v-if="questionsSyncedToDescriptor.length">
        <p>By deleting this descriptor, the following questions will be unsynced from it:</p>
        <ul>
          <li v-for="(question, questionIndex) in questionsSyncedToDescriptor"
              :key="`questions-synced-to-descriptor-${questionIndex}`"
          >
            {{ question.id }}: {{ question.title }}
          </li>
        </ul>
      </div>
      <div v-if="!questionsSyncedToDescriptor.length">
        By deleting the descriptor, it will no longer be connected to this framework.
      </div>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-confirm-delete-descriptor')"
        >
          Cancel
        </b-button>
        <b-button
          variant="danger"
          size="sm"
          class="float-right"
          @click="handleDeleteDescriptor"
        >
          Delete
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-delete-framework-level"
             :title="`Confirm delete ${frameworkLevelToDelete.title}`"
    >
      <div v-if="!getFrameworkLevelAndChildrenDescriptors(frameworkLevelToDelete).length">
        You are about to delete <strong>{{ frameworkLevelToDelete.title }}</strong>. This action cannot be undone.
      </div>
      <div v-if="getFrameworkLevelAndChildrenDescriptors(frameworkLevelToDelete).length">
        <p>If you delete the framework level, you'll need to either move the following descriptors or delete them:</p>
        <ul>
          <li v-for="(descriptor, descriptorIndex) in getFrameworkLevelAndChildrenDescriptors(frameworkLevelToDelete)"
              :key="`descriptor-${descriptorIndex}`"
          >
            <span v-html="descriptor.descriptor" />
          </li>
        </ul>
        <b-form-radio-group
          v-model="descriptorActionForFrameworkDelete"
          stacked
        >
          <b-form-radio name="delete_level_descriptors_option" value="delete">
            Delete the descriptors
            <QuestionCircleTooltip
              id="delete-descriptors-tooltip"
            />
            <b-tooltip target="delete-descriptors-tooltip"
                       delay="250"
                       triggers="hover focus"
            >
              Deleting the descriptors from the framework level will also unsync any questions associated with the
              descriptors.
            </b-tooltip>
          </b-form-radio>
          <b-form-radio name="delete_level_descriptors_option" value="move">
            Move the descriptors
          </b-form-radio>
        </b-form-radio-group>
        <div v-if="descriptorActionForFrameworkDelete === 'move'">
          <div v-for="(level, levelIndex) in levels" :key="`move-descriptors-to-level-${levelIndex}`">
            <b-form-group
              label-cols-sm="3"
              label-cols-lg="2"
              :label="`Level ${levelIndex+1}`"
            >
              <b-form-select :id="`level-${levelIndex+1}`"
                             v-model="level.value"
                             :options="level.options"
                             @change="getLevelOptions(levelIndex+1,level.value)"
              />
            </b-form-group>
          </div>
        </div>
      </div>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-confirm-delete-framework-level')"
        >
          Cancel
        </b-button>
        <b-button
          variant="danger"
          size="sm"
          class="float-right"
          @click="handleDeleteLevel"
        >
          Delete
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-move-descriptor"
             :title="`Move ${descriptorToMove.descriptor} to another level`"
    >
      <div v-for="(level, levelIndex) in levels" :key="`level-${levelIndex}`">
        <b-form-group
          label-cols-sm="3"
          label-cols-lg="2"
          :label="`Level ${levelIndex+1}`"
        >
          <b-form-select :id="`level-${levelIndex+1}`"
                         v-model="level.value"
                         :options="level.options"
                         @change="getLevelOptions(levelIndex+1,level.value)"
          />
        </b-form-group>
      </div>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-move-descriptor')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="handleMoveDescriptor"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-move-level"
             :title="`Move ${levelFrom.title}`"
    >
      <b-form-group>
        <b-form-row class="pt-2">
          <b-form-radio-group
            v-model="moveToOption"
          >
            <b-form-radio name="move_to_option" value="new level">
              To different level
            </b-form-radio>
            <b-form-radio name="move_to_option" value="new position">
              To a new position within its current level
            </b-form-radio>
          </b-form-radio-group>
        </b-form-row>
      </b-form-group>
      <b-form-group
        v-if="moveToOption=== 'new position'"
        label-cols-sm="4"
        label-cols-lg="3"
        label="Place after"
      >
        <b-form-select
          id="framework-level-position"
          v-model="frameworkLevelPosition"
          :options="frameworkLevelPositionOptions"
        />
      </b-form-group>
      <div v-if="moveToOption === 'new level'">
        <b-form-group>
          <b-form-row class="pt-2">
            <b-form-radio-group
              v-model="moveToOptionIsTopLevel"
            >
              <b-form-radio name="move_to_option_is_top_level" value="1">
                Move to the top level of the framework
              </b-form-radio>
              <b-form-radio name="move_to_option_is_top_level" value="0">
                Move within one of the levels
              </b-form-radio>
            </b-form-radio-group>
          </b-form-row>
        </b-form-group>

        <div v-if="+moveToOptionIsTopLevel === 0">
          <p>Choose the parent of the new level</p>
          <div v-for="(level, levelIndex) in levels" :key="`level-${levelIndex}`">
            <b-form-group
              v-if="levelIndex <3"
              label-cols-sm="3"
              label-cols-lg="2"
              :label="`Level ${levelIndex+1}`"
            >
              <b-form-select :id="`level-${levelIndex+1}`"
                             v-model="level.value"
                             :options="level.options"
                             @change="getLevelOptions(levelIndex+1,level.value)"
              />
            </b-form-group>
          </div>
        </div>
      </div>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-move-level')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="handleMoveLevel"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-add-update-descriptor"
             :title="descriptorToEdit ? `Edit ${descriptorForm.descriptor}` : `Add descriptor to ${descriptorsPath}`"
             size="lg"
    >
      <b-container fluid>
        <b-row>
          <b-col sm="2">
            <label for="new-descriptor">Descriptor*</label>
          </b-col>
          <b-col sm="10">
            <b-form-textarea
              id="new-descriptor"
              v-model="descriptorForm.descriptor"
              size="sm"
              type="text"
              :class="{ 'is-invalid': descriptorForm.errors.has('descriptor') }"
              @keydown="descriptorForm.errors.clear('descriptor')"
            />
            <has-error :form="descriptorForm" field="descriptor" />
          </b-col>
        </b-row>
      </b-container>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-add-update-descriptor')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="handleAddUpdateDescriptor"
        >
          Save
        </b-button>
      </template>
    </b-modal>

    <b-modal id="modal-edit-framework-level"
             :title="`Edit ${updateLevelForm.title}`"
    >
      <b-form-group
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="title"
        label="Title*"
      >
        <b-form-row>
          <b-form-input
            id="edit-title"
            v-model="updateLevelForm.title"
            required
            type="text"
            :class="{ 'is-invalid': updateLevelForm.errors.has('title') }"
            @keydown="updateLevelForm.errors.clear('title')"
          />
          <has-error :form="updateLevelForm" field="title" />
        </b-form-row>
      </b-form-group>

      <b-form-group
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="title"
        label="Description*"
      >
        <b-form-row>
          <b-form-input
            id="update-description"
            v-model="updateLevelForm.description"
            required
            type="text"
          />
        </b-form-row>
      </b-form-group>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-edit-framework-level')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="handleUpdateFrameworkLevel"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal id="add-framework-level"
             :title="`Add Level ${addLevelForm.level_to_add}`"
    >
      <RequiredText :plural="false" />
      <b-form-group
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="title"
        label="Title*"
      >
        <b-form-row>
          <b-form-input
            id="title"
            v-model="addLevelForm.title"
            required
            type="text"
            :class="{ 'is-invalid': addLevelForm.errors.has('title') }"
            @keydown="addLevelForm.errors.clear('title')"
          />
          <has-error :form="addLevelForm" field="title" />
        </b-form-row>
      </b-form-group>
      <b-form-group
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="description"
        label="Description"
      >
        <b-form-row>
          <b-form-input
            id="description"
            v-model="addLevelForm.description"
            required
            type="text"
          />
        </b-form-row>
      </b-form-group>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('add-framework-level')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="handleAddFrameworkLevel"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <div v-if="isEmbedded">
      <p>Choose a framework, syncing framework levels/descriptors by clicking on the associated text.</p>
      <b-form-row class="pb-2">
        <b-form-select
          id="framework"
          v-model="addLevelForm.framework_id"
          style="width:250px"
          size="sm"
          required
          :options="frameworkOptions"
          @change="getFrameworkLevels($event)"
        />
        <span v-if="loadingFramework" class="pl-2">
          <b-spinner small type="grow" /> Loading
        </span>
      </b-form-row>
    </div>
    <div v-if="!frameworkLevels.length && !loadingFramework && !isEmbedded">
      <b-alert show variant="info">
        This framework has no framework levels nor descriptors.
      </b-alert>
    </div>
    <div v-if="frameworkLevels.length" class="d-inline-flex pb-2">
      <b-input-group style="width:400px" class="pr-2">
        <b-form-input
          id="query"
          v-model="search"
          placeholder="Search framework levels or descriptors"
          required
          type="text"
          size="sm"
        />
        <b-input-group-append>
          <b-button variant="primary" size="sm" @click="doSearch">
            Search
          </b-button>
        </b-input-group-append>
      </b-input-group>
      <span v-if="isFrameworkOwner">
        <span class="pr-2 pointer" style="margin-top:1px">
          <b-icon-eye id="show-all-icons-tooltip"
                      scale="1.25"
                      @click="toggleShowAllIcons(true)"
          />
        </span>
        <span class="pr-2 pointer" style="margin-top:1px">
          <b-icon-eye-slash
            id="hide-all-icons-tooltip"
            scale="1.25"
            @click="toggleShowAllIcons(false)"
          />
        </span>
      </span>
      <span id="expand-all-tooltip" class="pr-2 pointer">
        <b-icon-arrows-expand scale="1.25"
                              @click="toggleExpandAll(false)"
        /></span>
      <span class="pr-2 pointer">
        <span id="collapse-all-tooltip" class="pr-2 pointer"> <b-icon-arrows-collapse scale="1.25"
                                                                                      @click="toggleExpandAll(true)"
        /></span>
        <span v-if="!isEmbedded && isFrameworkOwner" class="pr-2 pointer" style="margin-top:1px">
          <b-icon-trash id="delete-framework-levels-tooltip"
                        scale="1.25"
                        @click="$bvModal.show('modal-confirm-delete-framework-levels-and-descriptors')"
          />
        </span>
        <b-tooltip
          target="delete-framework-levels-tooltip"
          delay="700"
          triggers="hover"
        >
          Delete all of the framework levels and descriptors.
        </b-tooltip>
        <b-tooltip
          target="show-all-icons-tooltip"
          delay="700"
          triggers="hover"
        >
          Show all of the action icons so that you can add/edit your framework levels/ descriptors, move your framework
          levels/descriptors or
          delete your framework levels/descriptors.
        </b-tooltip>

        <b-tooltip
          target="hide-all-icons-tooltip"
          delay="700"
          triggers="hover"
        >
          Hide all of the action icons.
        </b-tooltip>
        <b-tooltip target="collapse-all-tooltip"
                   delay="700"
                   triggers="hover"
        >
          Collapse all framework levels.
        </b-tooltip>
        <b-tooltip target="expand-all-tooltip"
                   delay="700"
                   triggers="hover"
        >
          Expand all framework levels.
        </b-tooltip>
      </span>
    </div>
    <div v-for="(frameworkLevel1,level1Index) in frameworkLevels" :key="`framework-level1-${level1Index}`">
      <div v-if="frameworkLevel1.level === 1">
        <FrameworkAlignerIconTooltip :item-id="frameworkLevel1.id" />
        {{ frameworkLevel1.order }}.
        <span class="pointer"
              :class="getClass('levels',frameworkLevel1)"
              @click="syncItem('levels',frameworkLevel1.id, frameworkLevel1.title)"
        ><span v-html="frameworkLevel1.title" />
        </span>
        <span v-show="!frameworkLevel1.showItemIcons && isFrameworkOwner" class="pointer">
          <FrameworkAlignerIconTooltip :item-id="frameworkLevel1.id" />
          <b-icon-eye :id="`show-level-${frameworkLevel1.id}`" @click="toggleShowItemIcons(frameworkLevel1)" />
        </span>
        <span v-show="frameworkLevel1.showItemIcons && isFrameworkOwner">
          <span class="pointer">
            <b-icon-eye-slash :id="`hide-level-${frameworkLevel1.id}`" @click="toggleShowItemIcons(frameworkLevel1)" />
          </span>
          <span :id="`add-new-level-${frameworkLevel1.id}`"
                class="text-primary pointer"
                @click="initAddLevel(1,0, frameworkLevel1.order)"
          >+</span>
          <b-icon-arrow-down-circle
            :id="`add-child-level-${frameworkLevel1.id}`"
            class="text-info pointer"
            @click="initAddLevel(2,frameworkLevel1.id)"
          />
          <b-icon-pencil
            :id="`edit-level-${frameworkLevel1.id}`"
            class="text-muted pointer"
            @click="initEditFrameworkLevel(frameworkLevel1)"
          />
          <b-icon-truck
            :id="`move-level-${frameworkLevel1.id}`"
            class="text-muted pointer"
            @click="initMoveLevel(frameworkLevel1,1)"
          />
          <b-icon-trash
            :id="`delete-level-${frameworkLevel1.id}`"
            class="text-muted pointer"
            @click="initDeleteFrameworkLevel(frameworkLevel1)"
          />
          <b-button :id="`add-descriptor-${frameworkLevel1.id}`"
                    size="sm" variant="success" style="padding:2px;line-height:.8" class="pointer"
                    @click="initAddDescriptor(frameworkLevel1.id)"
          >
            D
          </b-button>
        </span>
        <span v-show="showCaret(frameworkLevel1,2)">
          <font-awesome-icon
            v-if="frameworkLevel1.hidden"
            :icon="caretRightIcon"
            size="lg"
            @click="frameworkLevel1.hidden= false"
          />
          <font-awesome-icon
            v-if="!frameworkLevel1.hidden"
            :icon="caretDownIcon"
            size="lg"
            @click="frameworkLevel1.hidden= true"
          />
        </span>
        <div v-show="!frameworkLevel1.hidden">
          <ul v-if="getDescriptors(frameworkLevel1)" class="mb-0 ml-4">
            <li
              v-for="(descriptorsLevel1,descriptorsLevel1Index) in getDescriptors(frameworkLevel1)"
              :key="`learning-objective-level1-${descriptorsLevel1Index}`"
            >
              <span class="pointer" :class="getClass('descriptors',descriptorsLevel1)"
                    @click="syncItem('descriptors',descriptorsLevel1.id, descriptorsLevel1.descriptor)"
              ><span v-html="descriptorsLevel1.descriptor" /></span>
              <span v-if="!descriptorsLevel1.showItemIcons && isFrameworkOwner" class="pointer">
                <b-icon-eye :id="`show-descriptor-${descriptorsLevel1.id}`"
                            @click="toggleShowItemIcons(descriptorsLevel1)"
                />
                <FrameworkAlignerIconTooltip :item-id="descriptorsLevel1.id" />
              </span>
              <span v-if="descriptorsLevel1.showItemIcons && isFrameworkOwner">
                <FrameworkAlignerIconTooltip :item-id="descriptorsLevel1.id" />
                <span class="pointer">
                  <b-icon-eye-slash :id="`hide-descriptor-${descriptorsLevel1.id}`"
                                    @click="toggleShowItemIcons(descriptorsLevel1)"
                  />
                </span>
                <b-icon-pencil
                  :id="`edit-descriptor-${descriptorsLevel1.id}`"
                  class="text-muted pointer"
                  @click="initEditDescriptor(descriptorsLevel1)"
                />
                <b-icon-truck
                  :id="`move-descriptor-${descriptorsLevel1.id}`"
                  class="text-muted pointer"
                  @click="initMoveDescriptor(descriptorsLevel1)"
                />
                <b-icon-trash
                  :id="`delete-descriptor-${descriptorsLevel1.id}`"
                  class="text-muted pointer"
                  @click="initDeleteDescriptor(descriptorsLevel1)"
                />
              </span>
            </li>
          </ul>
          <div v-for="(frameworkLevel2,level2Index) in frameworkLevels" :key="`framework-level2-${level2Index}`"
               class="pl-4"
          >
            <div
              v-if="frameworkLevel2.level === 2 && frameworkLevel2.parent_id === frameworkLevel1.id"
              class="pb-2"
            >
              {{ String.fromCharCode(96 + frameworkLevel2.order).toUpperCase() }}.
              <span class="pointer" :class="getClass('levels',frameworkLevel2)"
                    @click="syncItem('levels',frameworkLevel2.id, frameworkLevel2.title)"
              ><span v-html="frameworkLevel2.title" />
              </span>

              <span v-if="!frameworkLevel2.showItemIcons && isFrameworkOwner" class="pointer">
                <FrameworkAlignerIconTooltip :item-id="frameworkLevel2.id" />
                <b-icon-eye :id="`show-level-${frameworkLevel2.id}`" @click="toggleShowItemIcons(frameworkLevel2)" />
              </span>
              <span v-if="frameworkLevel2.showItemIcons && isFrameworkOwner">
                <FrameworkAlignerIconTooltip :item-id="frameworkLevel2.id" />
                <span class="pointer">
                  <b-icon-eye-slash :id="`hide-level-${frameworkLevel2.id}`"
                                    @click="toggleShowItemIcons(frameworkLevel2)"
                  />
                </span>
                <span :id="`add-new-level-${frameworkLevel2.id}`"
                      class="text-primary pointer"
                      @click="initAddLevel(2,frameworkLevel2.parent_id,frameworkLevel2.order)"
                >+</span>
                <b-icon-arrow-down-circle
                  :id="`add-child-level-${frameworkLevel2.id}`"
                  class="text-info pointer"
                  @click="initAddLevel(3,frameworkLevel2.id)"
                />
                <b-icon-pencil
                  :id="`edit-level-${frameworkLevel2.id}`"
                  class="text-muted pointer"
                  @click="initEditFrameworkLevel(frameworkLevel2)"
                />
                <b-icon-truck
                  :id="`move-level-${frameworkLevel2.id}`"
                  class="text-muted pointer"
                  @click="initMoveLevel(frameworkLevel2,2)"
                />
                <b-icon-trash
                  :id="`delete-level-${frameworkLevel2.id}`"
                  class="text-muted pointer"
                  @click="initDeleteFrameworkLevel(frameworkLevel2)"
                />
                <b-button :id="`add-descriptor-${frameworkLevel2.id}`"
                          size="sm" variant="success" style="padding:2px;line-height:.8" class="pointer"
                          @click="initAddDescriptor(frameworkLevel2.id)"
                >
                  D
                </b-button>
              </span>
              <span v-show="showCaret(frameworkLevel2,3)">
                <font-awesome-icon
                  v-if="frameworkLevel2.hidden"
                  :icon="caretRightIcon"
                  size="lg"
                  @click="frameworkLevel2.hidden= false"
                />
                <font-awesome-icon
                  v-if="!frameworkLevel2.hidden"
                  :icon="caretDownIcon"
                  size="lg"
                  @click="frameworkLevel2.hidden= true"
                />
              </span>
              <div v-show="!frameworkLevel2.hidden">
                <ul v-if="getDescriptors(frameworkLevel2)" class="mb-0 ml-4">
                  <li
                    v-for="(descriptorsLevel2,descriptorsLevel2Index) in getDescriptors(frameworkLevel2)"
                    :key="`learning-objective-level2-${descriptorsLevel2Index}`"
                  >
                    <span class="pointer" :class="getClass('descriptors',descriptorsLevel2)"
                          @click="syncItem('descriptors',descriptorsLevel2.id, descriptorsLevel2.descriptor)"
                    ><span v-html="descriptorsLevel2.descriptor" /></span>
                    <span v-if="!descriptorsLevel2.showItemIcons && isFrameworkOwner">
                      <FrameworkAlignerIconTooltip
                        :item-id="descriptorsLevel2.id"
                      />
                      <b-icon-eye :id="`show-descriptor-${descriptorsLevel2.id}`"
                                  @click="toggleShowItemIcons(descriptorsLevel2)"
                      />
                    </span>
                    <span v-if="descriptorsLevel2.showItemIcons && isFrameworkOwner">
                      <FrameworkAlignerIconTooltip
                        :item-id="descriptorsLevel2.id"
                      />
                      <span class="pointer">
                        <b-icon-eye-slash :id="`hide-descriptor-${descriptorsLevel2.id}`"
                                          @click="toggleShowItemIcons(descriptorsLevel2)"
                        />
                      </span>
                      <b-icon-pencil
                        class="text-muted pointer"
                        @click="initEditDescriptor(descriptorsLevel2)"
                      />
                      <b-icon-truck
                        :id="`move-descriptor-${descriptorsLevel2.id}`"
                        class="text-muted pointer"
                        @click="initMoveDescriptor(descriptorsLevel2)"
                      />
                      <b-icon-trash
                        :id="`delete-descriptor-${descriptorsLevel2.id}`"
                        class="text-muted pointer"
                        @click="initDeleteDescriptor(descriptorsLevel2)"
                      />
                    </span>
                  </li>
                </ul>
                <div v-for="(frameworkLevel3,level3Index) in frameworkLevels" :key="`framework-level3-${level3Index}`"
                     class="pl-4"
                >
                  <div v-if="frameworkLevel3.level === 3 && frameworkLevel3.parent_id === frameworkLevel2.id">
                    <FrameworkAlignerIconTooltip :item-id="frameworkLevel3.id" />
                    {{ convertToRoman(frameworkLevel3.order) }}.
                    <span class="pointer"
                          :class="getClass('levels',frameworkLevel3)"
                          @click="syncItem('levels',frameworkLevel3.id, frameworkLevel3.title)"
                    ><span v-html="frameworkLevel3.title" />
                    </span>
                    <span v-if="!frameworkLevel3.showItemIcons && isFrameworkOwner" class="pointer">
                      <FrameworkAlignerIconTooltip :item-id="frameworkLevel3.id" />
                      <b-icon-eye :id="`show-level-${frameworkLevel3.id}`"
                                  @click="toggleShowItemIcons(frameworkLevel3)"
                      />
                    </span>
                    <span v-if="frameworkLevel3.showItemIcons && isFrameworkOwner">
                      <FrameworkAlignerIconTooltip :item-id="frameworkLevel3.id" />
                      <span class="pointer">
                        <b-icon-eye-slash :id="`hide-level-${frameworkLevel3.id}`"
                                          @click="toggleShowItemIcons(frameworkLevel3)"
                        />

                      </span>
                      <span :id="`add-new-level-${frameworkLevel3.id}`"
                            class="text-primary pointer"
                            @click="initAddLevel(3,frameworkLevel3.parent_id,frameworkLevel3.order)"
                      >+</span>
                      <b-icon-arrow-down-circle
                        :id="`add-child-level-${frameworkLevel3.id}`"
                        class="text-info pointer"
                        @click="initAddLevel(4,frameworkLevel3.id)"
                      />
                      <b-icon-pencil
                        :id="`edit-level-${frameworkLevel3.id}`"
                        class="text-muted pointer"
                        @click="initEditFrameworkLevel(frameworkLevel3)"
                      />
                      <b-icon-truck
                        :id="`move-level-${frameworkLevel3.id}`"
                        class="text-muted pointer"
                        @click="initMoveLevel(frameworkLevel3,3)"
                      />
                      <b-icon-trash
                        :id="`delete-level-${frameworkLevel3.id}`"
                        class="text-muted pointer"
                        @click="initDeleteFrameworkLevel(frameworkLevel3)"
                      />
                      <b-button :id="`add-descriptor-${frameworkLevel3.id}`"
                                size="sm" variant="success" style="padding:2px;line-height:.8" class="pointer"
                                @click="initAddDescriptor(frameworkLevel3.id)"
                      >
                        D
                      </b-button>
                    </span>
                    <span v-show="showCaret(frameworkLevel3,4)">
                      <font-awesome-icon
                        v-if="frameworkLevel3.hidden"
                        :icon="caretRightIcon"
                        size="lg"
                        @click="frameworkLevel3.hidden= false"
                      />
                      <font-awesome-icon
                        v-if="!frameworkLevel3.hidden"
                        :icon="caretDownIcon"
                        size="lg"
                        @click="frameworkLevel3.hidden= true"
                      />
                    </span>
                    <div v-show="!frameworkLevel3.hidden">
                      <ul v-if="getDescriptors(frameworkLevel3)" class="mb-0 ml-4">
                        <li
                          v-for="(descriptorsLevel3,descriptorsLevel3Index) in getDescriptors(frameworkLevel3)"
                          :key="`learning-objective-level3-${descriptorsLevel3Index}`"
                        >
                          <span class="pointer"
                                :class="getClass('descriptors',descriptorsLevel3)"
                                @click="syncItem('descriptors',descriptorsLevel3.id, descriptorsLevel3.descriptor)"
                          ><span v-html="descriptorsLevel3.descriptor" /></span>
                          <span v-if="!descriptorsLevel3.showItemIcons && isFrameworkOwner" class="pointer">
                            <b-icon-eye :id="`show-descriptor-${descriptorsLevel3.id}`"
                                        @click="toggleShowItemIcons(descriptorsLevel3)"
                            />
                            <FrameworkAlignerIconTooltip
                              :item-id="descriptorsLevel3.id"
                            />
                          </span>
                          <span v-if="descriptorsLevel3.showItemIcons && isFrameworkOwner">
                            <FrameworkAlignerIconTooltip
                              :item-id="descriptorsLevel3.id"
                            />
                            <span class="pointer">
                              <b-icon-eye-slash :id="`hide-descriptor-${descriptorsLevel3.id}`"
                                                @click="toggleShowItemIcons(descriptorsLevel3)"
                              />

                            </span>
                            <b-icon-pencil
                              class="text-muted pointer"
                              @click="initEditDescriptor(descriptorsLevel3)"
                            />
                            <b-icon-truck
                              :id="`move-descriptor-${descriptorsLevel3.id}`"
                              class="text-muted pointer"
                              @click="initMoveDescriptor(descriptorsLevel3)"
                            />
                            <b-icon-trash
                              :id="`delete-descriptor-${descriptorsLevel3.id}`"
                              class="text-muted pointer"
                              @click="initDeleteDescriptor(descriptorsLevel3)"
                            />
                          </span>
                        </li>
                      </ul>
                      <div v-for="(frameworkLevel4,level4Index) in frameworkLevels"
                           :key="`framework-level4-${level4Index}`"
                           class="pl-4"
                      >
                        <div
                          v-if="frameworkLevel4.level === 4 && frameworkLevel4.parent_id === frameworkLevel3.id"
                        >
                          {{ String.fromCharCode(96 + frameworkLevel4.order) }}.
                          <span class="pointer"
                                :class="getClass('levels',frameworkLevel4)"
                                @click="syncItem('levels',frameworkLevel4.id, frameworkLevel4.title)"
                          >
                            <span v-html="frameworkLevel4.title" />
                          </span>
                          <span v-if="!frameworkLevel4.showItemIcons && isFrameworkOwner" class="pointer">
                            <FrameworkAlignerIconTooltip :item-id="frameworkLevel4.id" />
                            <b-icon-eye :id="`show-level-${frameworkLevel4.id}`"
                                        @click="toggleShowItemIcons(frameworkLevel4)"
                            />

                          </span>
                          <span v-if="frameworkLevel4.showItemIcons && isFrameworkOwner">
                            <FrameworkAlignerIconTooltip :item-id="frameworkLevel4.id" />
                            <span class="pointer">
                              <b-icon-eye-slash :id="`hide-level-${frameworkLevel4.id}`"
                                                @click="toggleShowItemIcons(frameworkLevel4)"
                              />
                            </span>
                            <span
                              :id="`add-new-level-${frameworkLevel1.id}`"
                              class="text-primary pointer"
                              @click="initAddLevel(4,frameworkLevel4.parent_id,frameworkLevel4.order)"
                            >+</span>
                            <b-icon-pencil
                              :id="`edit-level-${frameworkLevel4.id}`"
                              class="text-muted pointer"
                              @click="initEditFrameworkLevel(frameworkLevel4)"
                            />
                            <b-icon-truck
                              :id="`move-level-${frameworkLevel4.id}`"
                              class="text-muted pointer"
                              @click="initMoveLevel(frameworkLevel4,4)"
                            />
                            <b-icon-trash
                              :id="`delete-level-${frameworkLevel4.id}`"
                              class="text-muted pointer"
                              @click="initDeleteFrameworkLevel(frameworkLevel4)"
                            />
                            <FrameworkAlignerIconTooltip
                              :item-id="frameworkLevel4.id"
                            />
                            <b-button :id="`add-descriptor-${frameworkLevel4.id}`"
                                      size="sm" variant="success" style="padding:2px;line-height:.8" class="pointer"
                                      @click="initAddDescriptor(frameworkLevel4.id)"
                            >
                              D
                            </b-button>
                          </span>
                          <span v-show="showCaret(frameworkLevel4,5)">
                            <font-awesome-icon
                              v-if="frameworkLevel4.hidden"
                              :icon="caretRightIcon"
                              size="lg"
                              @click="frameworkLevel4.hidden= false"
                            />
                            <font-awesome-icon
                              v-if="!frameworkLevel4.hidden"
                              :icon="caretDownIcon"
                              size="lg"
                              @click="frameworkLevel4.hidden= true"
                            />
                          </span>
                          <div v-show="!frameworkLevel4.hidden">
                            <ul v-if="getDescriptors(frameworkLevel4)" class="mb-0 ml-4">
                              <li
                                v-for="(descriptorsLevel4,descriptorsLevel4Index) in getDescriptors(frameworkLevel4)"
                                :key="`learning-objective-level3-${descriptorsLevel4Index}`"
                              >
                                <span class="pointer"
                                      :class="getClass('descriptors',descriptorsLevel4)"
                                      @click="syncItem('descriptors',descriptorsLevel4.id, descriptorsLevel4.descriptor)"
                                >
                                  {{ descriptorsLevel4.descriptor }}
                                </span>
                                <span v-if="!descriptorsLevel4.showItemIcons && isFrameworkOwner" class="pointer">
                                  <FrameworkAlignerIconTooltip
                                    :item-id="descriptorsLevel4.id"
                                  />
                                  <b-icon-eye :id="`show-descriptor-${descriptorsLevel4.id}`"
                                              @click="toggleShowItemIcons(descriptorsLevel4)"
                                  />

                                </span>
                                <span v-if="descriptorsLevel4.showItemIcons && isFrameworkOwner">
                                  <FrameworkAlignerIconTooltip
                                    :item-id="descriptorsLevel4.id"
                                  />
                                  <span class="pointer">
                                    <b-icon-eye-slash @click="toggleShowItemIcons(descriptorsLevel4)" />
                                  </span>
                                  <b-icon-pencil
                                    class="text-muted pointer"
                                    @click="initEditDescriptor(descriptorsLevel4)"
                                  />
                                  <b-icon-truck
                                    :id="`move-descriptor-${descriptorsLevel4.id}`"
                                    class="text-muted pointer"
                                    @click="initMoveDescriptor(descriptorsLevel4)"
                                  />
                                  <b-icon-trash
                                    :id="`delete-descriptor-${descriptorsLevel4.id}`"
                                    class="text-muted pointer"
                                    @click="initDeleteDescriptor(descriptorsLevel4)"
                                  />

                                </span>
                              </li>
                            </ul>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Form from 'vform'
import AllFormErrors from './AllFormErrors'
import { faCaretDown, faCaretRight } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { mapGetters } from 'vuex'
import FrameworkAlignerIconTooltip from '~/components/FrameworkAlignerIconTooltip'

export default {
  name: 'FrameworkAligner',
  components: {
    AllFormErrors,
    FontAwesomeIcon,
    FrameworkAlignerIconTooltip
  },
  props: {
    isEmbedded: {
      type: Boolean,
      default: false
    },
    frameworkId: {
      type: Number,
      default: 0
    },
    itemId: {
      type: Number,
      default: 0
    },
    frameworkItemSync: {
      type: Object,
      default: () => ({ descriptors: [], levels: [] })
    }
  },
  data: () => ({
    loadingFramework: false,
    isFrameworkOwner: false,
    showAllIcons: false,
    questionsSyncedToDescriptor: [],
    descriptorToDelete: 0,
    moveToOptionIsTopLevel: 0,
    descriptorActionForFrameworkDelete: '',
    allChildren: [],
    frameworkLevelToDelete: 0,
    showItemIcons: false,
    matches: [],
    search: '',
    descriptorToEdit: 0,
    caretDownIcon: faCaretDown,
    caretRightIcon: faCaretRight,
    descriptorToMove: 0,
    frameworkLevelPosition: 0,
    frameworkLevelPositionOptions: [],
    levelsWithSameParent: [],
    moveToOption: '',
    levelFrom: {},
    levels: [{}, {}, {}, {}],
    descriptorFormErrors: [],
    descriptorsPath: '',
    allFormErrors: [],
    descriptorForm: new Form({
      descriptor: ''
    }),
    addLevelForm: new Form({
      order: 0,
      title: '',
      level_to_add: 0,
      framework_id: null
    }),
    updateLevelForm: new Form({
      title: '',
      framework_level_id: 0
    }),
    frameworkLevels: [],
    descriptors: [],
    framework: null,
    frameworkOptions: [{ text: 'Please choose a framework', value: null }]
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    })
  },
  mounted () {
    for (let i = 0; i < 4; i++) {
      this.levels[i] = { value: null, options: [{ value: null, text: 'Please choose a level' }] }
    }
    if (this.frameworkId) {
      this.addLevelForm.framework_id = this.frameworkId
      this.getFrameworkLevels(this.frameworkId)
    } else {
      this.getFrameworks()
    }
  },
  methods: {
    async handleDeleteFrameworkLevelsAndDescriptors () {
      try {
        const { data } = await axios.delete(`/api/frameworks/${this.frameworkId}/0`)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        await this.getFrameworkLevels(this.frameworkId)
        this.$bvModal.hide('modal-confirm-delete-framework-levels-and-descriptors')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    toggleShowAllIcons (showItemIcons) {
      for (let i = 0; i < this.frameworkLevels.length; i++) {
        this.frameworkLevels[i].showItemIcons = showItemIcons
      }
      for (let i = 0; i < this.descriptors.length; i++) {
        this.descriptors[i].showItemIcons = showItemIcons
      }
      this.$root.$emit('bv::hide::tooltip')
    },
    toggleShowItemIcons (item) {
      item.showItemIcons = !item.showItemIcons
      this.$forceUpdate()
    },
    async initDeleteDescriptor (descriptor) {
      this.descriptorToDelete = descriptor
      try {
        const { data } = await axios.get(`/api/framework-item-sync-question/get-questions-by-descriptor/${descriptor.id}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.questionsSyncedToDescriptor = data.questions_synced_to_descriptor
        this.$bvModal.show('modal-confirm-delete-descriptor')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async handleDeleteDescriptor () {
      try {
        const { data } = await axios.delete(`/api/framework-descriptors/${this.descriptorToDelete.id}`)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        await this.getFrameworkLevels(this.frameworkId, false)
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.$bvModal.hide('modal-confirm-delete-descriptor')
    },
    async handleDeleteLevel () {
      if (!this.getFrameworkLevelAndChildrenDescriptors(this.frameworkLevelToDelete).length) {
        this.descriptorActionForFrameworkDelete = 'none-exist'
      }
      if (!this.descriptorActionForFrameworkDelete) {
        this.$noty.info('Please choose one of the descriptor options provided.')
        return false
      }
      let levelToId = 0
      if (this.descriptorActionForFrameworkDelete === 'move') {
        levelToId = this.getLevelToMoveTo()
        if (!levelToId) {
          this.$noty.info('Please pick a level to move the descriptors to.')
          return false
        }
      }
      try {
        const { data } = await axios.delete(`/api/framework-levels/${this.frameworkLevelToDelete.id}/descriptor-action/${this.descriptorActionForFrameworkDelete}/level-to-move-to/${levelToId}`)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        this.$bvModal.hide('modal-confirm-delete-framework-level')
        await this.getFrameworkLevels(this.frameworkId, false)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getFrameworkLevelAndChildrenDescriptors (frameworkLevel) {
      return this.descriptors.filter(item => this.allChildren.includes(item.framework_level_id) || item.framework_level_id === frameworkLevel.id)
    },
    async initDeleteFrameworkLevel (frameworkLevel) {
      this.initLevels()
      await this.getLevelOptions(0, 0)
      this.moveToOption = ''
      this.descriptorActionForFrameworkDelete = ''
      this.frameworkLevelToDelete = frameworkLevel
      await this.getAllChildren(frameworkLevel)
      this.$bvModal.show('modal-confirm-delete-framework-level')
    },
    async getAllChildren (frameworkLevel) {
      try {
        const { data } = await axios.get(`/api/framework-levels/all-children/${frameworkLevel.id}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.allChildren = data.all_children
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    doSearch () {
      if (!this.search) {
        this.$noty.info('Please enter a search term.')
        return false
      }
      this.matches = []
      let searchResults = document.getElementsByClassName('bg-search')
      for (let i = 0; i < searchResults.length; i++) {
        searchResults[i].classList.remove('bg-search')
      }
      for (let i = 0; i < this.frameworkLevels.length; i++) {
        this.frameworkLevels[i].hidden = true
      }
      for (let i = 0; i < this.frameworkLevels.length; i++) {
        let level = this.frameworkLevels[i]
        if (level.title.toLowerCase().includes(this.search.toLowerCase())) {
          this.matches.push(level)
          level.hidden = false
          level.searchResult = true
          let childLevel = level
          for (let j = 0; j < 4; j++) {
            if (childLevel.parent_id) {
              let parent = this.frameworkLevels.find(item => item.id === childLevel.parent_id)
              parent.hidden = false
              childLevel = parent
            }
          }
        }
      }
      for (let i = 0; i < this.descriptors.length; i++) {
        let descriptor = this.descriptors[i]
        if (descriptor.descriptor.toLowerCase().includes(this.search.toLowerCase())) {
          this.matches.push(descriptor)
          let levelId = this.descriptors[i].framework_level_id
          let level = this.frameworkLevels.find(item => item.id === levelId)
          level.hidden = false
          descriptor.searchResult = true
          let childLevel = level
          for (let j = 0; j < 4; j++) {
            if (childLevel.parent_id) {
              let parent = this.frameworkLevels.find(item => item.id === childLevel.parent_id)
              parent.hidden = false
              childLevel = parent
            }
          }
        }
      }
      if (!this.matches.length) {
        this.$noty.info('No matches found.')
        return true
      }
      this.search = ''
    },
    toggleExpandAll (expandAll) {
      for (let i = 0; i < this.frameworkLevels.length; i++) {
        this.frameworkLevels[i].hidden = expandAll
      }
    },
    initEditDescriptor (descriptor) {
      this.descriptorForm.descriptor = descriptor.descriptor
      this.descriptorToEdit = descriptor.id
      this.$bvModal.show('modal-add-update-descriptor')
    },
    initEditFrameworkLevel (frameworkLevel) {
      this.updateLevelForm.framework_level_id = frameworkLevel.id
      this.updateLevelForm.title = frameworkLevel.title
      this.updateLevelForm.description = frameworkLevel.description
      this.$bvModal.show('modal-edit-framework-level')
    },
    showCaret (frameworkLevel, level) {
      return this.getDescriptors(frameworkLevel).length > 0 ||
        (this.frameworkLevels.filter(item => item.level === level && item.parent_id === frameworkLevel.id).length > 0)
    },
    async handleMoveDescriptor () {
      let levelToId = this.getLevelToMoveTo()
      if (!levelToId) {
        this.$noty.info('Please pick a level to move to.')
        return false
      }
      let descriptorToMoveInfo = {
        descriptor_id: this.descriptorToMove.id,
        level_from_id: this.descriptorToMove.framework_level_id,
        level_to_id: levelToId
      }
      try {
        const { data } = await axios.patch('/api/framework-descriptors/move', descriptorToMoveInfo)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        this.$bvModal.hide('modal-move-descriptor')
        await this.getFrameworkLevels(this.frameworkId, false)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async initMoveDescriptor (descriptor) {
      this.initLevels()
      this.descriptorToMove = descriptor
      await this.getLevelOptions(0, 0)
      this.$bvModal.show('modal-move-descriptor')
    },
    getLevelToMoveTo () {
      for (let i = 0; i < 4; i++) {
        if (this.levels[3 - i].value) {
          return this.levels[3 - i].value
        }
      }
      return null
    },
    async initMoveLevel (levelFrom) {
      this.initLevels()
      this.moveToOption = ''
      this.moveToOptionIsTopLevel = 1
      this.levelFrom = levelFrom
      await this.getLevelOptions(0, 0)
      this.frameworkLevelPositionOptions = []
      await this.getLevelsWithSameParent(levelFrom.id)
      this.$bvModal.show('modal-move-level')
    },
    async handleMoveLevel () {
      try {
        let moveToInfo
        let url
        switch (this.moveToOption) {
          case ('new level'):
            let levelToId = this.getLevelToMoveTo()
            if (!levelToId && !this.moveToOptionIsTopLevel) {
              this.$noty.info('Please pick a level to move to.')
              return false
            }
            moveToInfo = {
              level_from_id: this.levelFrom.id,
              level_to_id: levelToId,
              move_to_option_is_top_level: this.moveToOptionIsTopLevel
            }
            url = `/api/framework-levels/move-level`
            break
          case ('new position'):
            moveToInfo = {
              position: this.frameworkLevelPosition,
              level_id: this.levelFrom.id
            }
            url = `/api/framework-levels/change-position`
            break
          default:
            this.$noty.info(`${this.moveToOption} is not a valid move to option.`)
            return false
        }
        const { data } = await axios.patch(url, moveToInfo)
        this.$noty[data.type](data.message)
        if (data.type !== 'success') {
          return false
        }
        this.$bvModal.hide('modal-move-level')
        await this.getFrameworkLevels(this.frameworkId, false)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getLevelOptions (levelIndex, parentId) {
      if (levelIndex === 4) {
        return
      }
      if (parentId === null) {
        for (let i = levelIndex - 1; i < 4; i++) {
          this.levels[levelIndex].options = [{ value: null, text: 'Please choose a level' }]
          this.levels[levelIndex].value = null
        }
        this.$forceUpdate()
        return false
      }
      try {
        const { data } = await axios.get(`/api/framework-levels/framework/${this.frameworkId}/parent-id/${parentId}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.levels[levelIndex].value = null
        let options = [{ value: null, text: 'Please choose a level' }]
        for (let i = 0; i < data.framework_level_children.length; i++) {
          let child = data.framework_level_children[i]
          options.push({ text: child.title, value: child.id })
        }
        this.levels[levelIndex].options = options
        this.$forceUpdate()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    initLevels () {
      this.$nextTick(() => {
        for (let i = 0; i < this.levels.length; i++) {
          this.levels[i].value = null
          this.levels[i].options = [{ value: null, text: 'Please choose a level' }]
          console.log(i)
        }
      })

      this.$forceUpdate()
    },
    async getLevelsWithSameParent (levelFrom) {
      try {
        const { data } = await axios.get(`/api/framework-levels/same-parent/${levelFrom}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.levelsWithSameParent = data.levels_with_same_parent

        for (let i = 0; i < this.levelsWithSameParent.length; i++) {
          let level = this.levelsWithSameParent[i]
          let option = { value: level.order, text: `${level.order} ${level.title}` }
          this.frameworkLevelPositionOptions.push(option)
          this.frameworkLevelPosition = 1
        }
        this.$forceUpdate()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getClass (itemType, item) {
      let itemClass = ''
      if (this.isEmbedded) {
        itemClass = this.frameworkItemSync[itemType].find(x => x.id === item.id) ? 'text-danger' : 'text-primary'
      }
      if (item.searchResult) {
        itemClass += ' bg-search'
      }
      return itemClass
    },
    async syncItem (itemType, itemId, itemText) {
      if (this.isEmbedded) {
        let syncedItem = this.frameworkItemSync[itemType].find(item => item.id === itemId)
        if (syncedItem) {
          this.frameworkItemSync[itemType] = this.frameworkItemSync[itemType].filter(item => item.id !== itemId)
        } else {
          this.frameworkItemSync[itemType].push({ id: itemId, text: itemText })
        }
        this.$emit('setFrameworkItemSync', this.frameworkItemSync)
      }
    },
    deleteDescriptor (index) {
      this.descriptorForm.descriptors.splice(index, 1)
    },
    async handleAddUpdateDescriptor () {
      try {
        const { data } = this.descriptorToEdit
          ? await this.descriptorForm.patch(`/api/framework-descriptors/${this.descriptorToEdit}`)
          : await this.descriptorForm.post('/api/framework-descriptors')
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        await this.getFrameworkLevels(this.addLevelForm.framework_id, false)
        this.$bvModal.hide('modal-add-update-descriptor')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.descriptorForm.errors.flatten()
          this.$bvModal.show('modal-descriptors-form-errors')
        }
      }
    },
    getDescriptors (frameworkLevel) {
      return this.descriptors.filter(item => item.framework_level_id === frameworkLevel.id)
    },
    initAddDescriptor (frameworkLevelId) {
      this.descriptorToEdit = 0
      this.descriptorForm.framework_level_id = frameworkLevelId
      this.descriptorsPath = this.getDescriptorsPath(frameworkLevelId)
      this.$bvModal.show('modal-add-update-descriptor')
    },
    getDescriptorsPath (frameworkLevel) {
      let currentItem = this.frameworkLevels.find(item => item.id === frameworkLevel)
      console.log(frameworkLevel)
      console.log(currentItem)
      let path = currentItem.title
      let parentFrameworkLevel = this.frameworkLevels.find(item => item.id === currentItem.parent_id)
      for (let i = 0; i < 4; i++) {
        if (parentFrameworkLevel) {
          path = `${parentFrameworkLevel.title}->${path}`
          console.log(path)
          parentFrameworkLevel = this.frameworkLevels.find(item => item.id === parentFrameworkLevel.parent_id)
        } else {
          return path
        }
      }
    },
    convertToRoman (num) {
      let roman = {
        M: 1000,
        CM: 900,
        D: 500,
        CD: 400,
        C: 100,
        XC: 90,
        L: 50,
        XL: 40,
        X: 10,
        IX: 9,
        V: 5,
        IV: 4,
        I: 1
      }
      let str = ''

      for (let i of Object.keys(roman)) {
        let q = Math.floor(num / roman[i])
        num -= q * roman[i]
        str += i.repeat(q)
      }

      return str
    },
    async handleUpdateFrameworkLevel () {
      try {
        const { data } = await this.updateLevelForm.patch('/api/framework-levels')
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        this.frameworkLevels.find(item => item.id === this.updateLevelForm.framework_level_id).title = this.updateLevelForm.title
        let syncedTag = this.frameworkItemSync['levels'].find(item => item.id === this.updateLevelForm.framework_level_id)
        if (syncedTag) {
          syncedTag.text = this.updateLevelForm.title
        }
        this.$emit('setFrameworkItemSync', this.frameworkItemSync)
        this.$bvModal.hide('modal-edit-framework-level')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.updateLevelForm.errors.flatten()
          this.$bvModal.show('modal-level-form-errors')
        }
      }
    },
    async handleAddFrameworkLevel () {
      try {
        const { data } = await this.addLevelForm.post('/api/framework-levels')
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        await this.getFrameworkLevels(this.addLevelForm.framework_id, false)
        this.$bvModal.hide('add-framework-level')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.addLevelForm.errors.flatten()
          this.$bvModal.show('modal-level-form-errors')
        }
      }
    },
    initAddLevel (levelToAdd, parentId, order = 0) {
      this.addLevelForm.title = ''
      this.addLevelForm.description = ''
      this.addLevelForm.order = order
      this.addLevelForm.level_to_add = levelToAdd
      this.addLevelForm.parent_id = parentId
      this.$bvModal.show('add-framework-level')
    },
    async getFrameworkLevels (framework, init = true) {
      if (!framework) {
        return false
      }
      this.loadingFramework = true
      try {
        const { data } = await axios.get(`/api/frameworks/${framework}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          this.loadingFramework = false
          return false
        }
        this.isFrameworkOwner = this.user.id === data.properties.user_id
        if (init) {
          for (let i = 0; i < data.framework_levels.length; i++) {
            data.framework_levels[i].hidden = !this.isEmbedded
          }
          for (let i = 0; i < data.descriptors; i++) {
            data.descriptors[i].showItemIcons = false
          }
        } else {
          for (let i = 0; i < data.framework_levels.length; i++) {
            let currentFrameworkLevel = this.frameworkLevels.find(level => level.id === data.framework_levels[i].id)
            data.framework_levels[i].hidden = currentFrameworkLevel ? currentFrameworkLevel.hidden : false
          }
        }
        for (let i = 0; i < data.framework_levels.length; i++) {
          data.framework_levels[i].showItemIcons = false
        }
        for (let i = 0; i < data.descriptors; i++) {
          data.descriptors[i].showItemIcons = false
        }
        this.frameworkLevels = data.framework_levels
        this.descriptors = data.descriptors

        this.$forceUpdate()
        this.$emit('setFrameworkInfo', data.properties)
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.loadingFramework = false
    },
    async getFrameworks () {
      try {
        const { data } = await axios.get('/api/frameworks')
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        for (let i = 0; i < data.frameworks.length; i++) {
          let framework = data.frameworks[i]
          this.frameworkOptions.push({
            value: framework.id,
            text: framework.title
          })
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>
<style scoped>
.pointer {
  cursor: pointer;
}

.bg-search {
  background-color: #FFFF8A;
}
</style>
