<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-file-upload'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-h5p-collection-import'"/>
    <b-modal id="modal-bulk-upload-instructions"
             title="Upload Instructions"
             size="lg"
             hide-footer
    >
      <ol v-if="['webwork','advanced'].includes(importTemplate)">
        <li>
          Starred fields are required.
        </li>
        <li v-if="importTemplate === 'advanced'">
          Question Type should be either assessment or exposition.
        </li>
        <li v-if="importTemplate === 'advanced'">
          Questions that are of type exposition should not have any associated technology nor should they contain
          Text Question, A11Y Question, Answer, Solution, or Hint
        </li>
        <li>
          Please enter 1 for yes and 0 for no in the Public* column.
        </li>
        <li v-if="importTemplate === 'advanced'">
          The source column may be left blank for assessment question types assuming that you are using one of the
          auto-graded technologies.
        </li>
        <li>Tags should be a comma separated list: tag 1, tag 2, tag 3.</li>
        <li>Accepted licenses are {{ validLicenses }}.</li>
        <li>
          License versions should be of the form x.y (i.e. 3.0, 4.0). If no license version is provided, Adapt
          will
          assume the most current license possible.
        </li>
        <li>
          Folders can be chosen from your list of <a href=""
                                                     @click.prevent="$bvModal.show('modal-my-questions-folders')"
        >My Questions folders</a> or you can create a new My Questions Folder while you import your questions.
        </li>
        <li>
          To upload your questions directly into an assignment, the assignment will need to first be created in
          the
          course or you will need to create an <a href="/instructors/assignment-templates" target="_blank">assignment
          template</a> and specify which template. Within these assignments
          you can further <a href=""
                             @click.prevent="$bvModal.show('modal-my-assignments-and-topics')"
        > categorize by topic</a> or create new topics as you import your questions.
        </li>
      </ol>
      <ol v-if="importTemplate === 'flashcard'">
        <li>Starred fields are required.</li>
        <li><strong>Front*</strong> is the question or term shown on the front of the card.</li>
        <li><strong>Front Text Language*</strong> is the language of the text on the front of the card.</li>
        <li><strong>Back*</strong> is the answer or definition shown on the back of the card.</li>
        <li><strong>Back Text Language*</strong> is the language of the text on the front of the card.</li>
        <li><strong>Hint</strong> and <strong>Tags</strong> are optional and may be left blank.</li>
        <li>Tags should be a comma separated list: tag 1, tag 2, tag 3.</li>
        <li>Please enter 1 for yes and 0 for no in the Public* column.</li>
        <li>Accepted licenses are {{ validLicenses }}.</li>
        <li>
          Folders can be chosen from your existing
          <a href="" @click.prevent="$bvModal.show('modal-my-questions-folders')">My Questions folders</a>
          or a new folder will be created automatically if the name you enter does not yet exist.
        </li>
        <li>
          If a card with the same Front and Back already exists in ADAPT it will be reused rather than duplicated.
        </li>
        <li>
          To add imported cards directly to an assignment, select a course and assignment before uploading.
        </li>
      </ol>
    </b-modal>
    <b-modal id="modal-my-assignments-and-topics"
             title="Assignments and Topics"
             hide-footer
    >
      <b-form-select
        id="per-page-select"
        v-model="course"
        :options="myCoursesOptions"
        @change="getAssignmentsAndTopicsByCourse($event)"
      />
      <ol class="mt-2">
        <li v-for="assignment in assignments" :key="`assignment-${assignment.id}`">
          <span :id="`copy-assignment-${assignment.id}`">{{ assignment.name }}</span> <a
          href="#"
          class="pr-1"
          aria-label="Copy Assignment"
          @click.prevent="doCopy(`copy-assignment-${assignment.id}`)"
        >
          <font-awesome-icon
            :icon="copyIcon"
          />
        </a>
          <ul v-if="assignment.topics.length">
            <li v-for="topic in assignment.topics" :key="`topic-${topic.id}`">
              <span :id="`copy-topic-${topic.id}`">{{ topic.name }}</span> <a
              href="#"
              class="pr-1"
              aria-label="Copy Topic"
              @click.prevent="doCopy(`copy-topic-${topic.id}`)"
            >
              <font-awesome-icon
                :icon="copyIcon"
              />
            </a>
            </li>
          </ul>
        </li>
      </ol>
    </b-modal>
    <b-modal id="modal-my-questions-folders"
             title="My Questions Folders"
             hide-footer
    >
      <div v-if="myQuestionsFolders.length">
        <ol>
          <li v-for="myQuestionsFolder in myQuestionsFolders" :key="`my-question-folder-${myQuestionsFolder.text}`">
            {{ myQuestionsFolder.text }}
          </li>
        </ol>
      </div>
      <div v-if="!myQuestionsFolders.length">
        <b-alert variant="info" show>
          <span class="font-weight-bold">You have no My Questions folders.</span>
        </b-alert>
      </div>
      <template #modal-footer="{ ok }">
        <b-button size="sm" variant="primary"
                  @click="$bvModal.hide('modal-my-questions-folders')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-container v-show="importTemplate !== 'case_study_notes'">
      <p>
        Instead of creating questions one at a time, you can bulk import them into ADAPT. Using the H5P importer, you
        can import a comma separated
        list of H5P questions directly into My Questions. Using the WebWork importer, you can import questions into My
        Questions as well as directly
        into a course. And, the advanced provides a way to import questions from advanced.
      </p>
      <b-form-group
        label-cols-sm="3"
        label-cols-lg="2"
        label="Import Template"
      >
        <b-form-row class="pt-2">
          <b-form-radio-group
            v-model="importTemplate"
            @change="setQuestionsToImport($event)"
          >
            <b-form-radio name="import_template" value="h5p">
              H5P
            </b-form-radio>
            <b-form-radio name="import_template" value="webwork">
              WeBWorK
            </b-form-radio>
            <b-form-radio name="import_template" value="advanced">
              MyOpenMath
            </b-form-radio>
            <b-form-radio name="import_template" value="qti">
              Canvas QTI
            </b-form-radio>
            <b-form-radio name="import_template" value="flashcard">
              Flashcard
            </b-form-radio>
            <b-form-radio v-show="user.id === 3280" name="import_template" value="bow_tie">
              Bowtie
            </b-form-radio>
          </b-form-radio-group>
        </b-form-row>
      </b-form-group>
    </b-container>

    <!-- ─── Flashcard Importer ──────────────────────────────────────────────── -->
    <div v-if="importTemplate === 'flashcard'">
      <b-card
        header-html="<h2 class=&quot;h7&quot;>Flashcard Importer</h2>"
        class="mb-4"
      >
        <p>
          Import text-only flashcards from a CSV file. Each row becomes one flashcard question.
          Download the template below to get started.
        </p>

        <b-button variant="secondary" size="sm" class="mb-3 mr-2" @click="$bvModal.show('modal-bulk-upload-instructions')">
          Instructions
        </b-button>
        <b-button variant="success" size="sm" class="mb-3" @click="downloadCSVStructure">
          Download Flashcard Import Template
        </b-button>

        <!-- Import to assignment (optional) -->
        <b-form-group
          label-cols-sm="3"
          label-cols-lg="2"
          label="Import to Assignment"
        >
          <b-form-row>
            <b-col cols="5">
              <b-form-select
                v-model="flashcardImportCourseId"
                size="sm"
                class="mt-1"
                :options="flashcardCourseOptions"
                @change="onFlashcardCourseChange"
              />
            </b-col>
            <b-col v-if="flashcardImportCourseId" cols="5">
              <b-form-select
                v-model="flashcardImportAssignmentId"
                size="sm"
                class="mt-1"
                :options="flashcardAssignmentOptions"
              />
            </b-col>
          </b-form-row>
        </b-form-group>

        <!-- File upload -->
        <b-form-group
          label-cols-sm="3"
          label-cols-lg="2"
          label="CSV File*"
        >
          <b-form-file
            v-model="flashcardImportFile"
            class="mb-2"
            accept=".csv"
            placeholder="Choose a CSV file or drop it here..."
            drop-placeholder="Drop file here..."
          />
          <div v-if="flashcardUploading">
            <b-spinner small type="grow"/>
            Uploading...
          </div>
        </b-form-group>

        <b-button
          variant="info"
          :disabled="!flashcardImportFile || flashcardUploading"
          @click="uploadFlashcardImportFile"
        >
          Import
        </b-button>
      </b-card>

      <!-- Error messages -->
      <div v-if="errorMessages.length" class="text-danger mb-3">
        Please fix the following errors:
        <ul>
          <li v-for="errorMessage in errorMessages" :key="errorMessage">
            {{ errorMessage }}
          </li>
        </ul>
      </div>

      <!-- Import summary + results table -->
      <div v-if="questionsToImport.length" style="min-height:200px">
        <b-container fluid>
          <h2 class="h5">Import Summary</h2>
          <table class="table table-striped pb-3" style="width:auto">
            <thead>
            <tr>
              <th scope="col" style="width:200px">Import Status</th>
              <th scope="col" style="width:200px">Count</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="item in questionsToImportSummary" :key="item.key">
              <td>
                <span v-if="parseInt(item.total) > 0">
                  <a href="" @click.prevent="filter = item.key">{{ item.key }}</a>
                </span>
                <span v-else>{{ item.key }}</span>
              </td>
              <td>{{ item.total }}</td>
            </tr>
            </tbody>
          </table>

          <h2 class="h5">Cards to Import</h2>
          <b-table
            striped
            hover
            responsive
            :filter="filter"
            :no-border-collapse="true"
            :fields="flashcardTableFields"
            :items="questionsToImport"
          >
            <template v-slot:cell(import_status)="data">
              <span v-html="data.item.import_status"/>
            </template>
          </b-table>
        </b-container>
      </div>
    </div>
    <!-- ─── End Flashcard Importer ──────────────────────────────────────────── -->

    <div v-show="importTemplate !== 'case_study_notes' && importTemplate !== 'flashcard'">
      <b-card
        v-if="importTemplate === 'h5p'"
        header-html="<h2 class=&quot;h7&quot;>H5P Importer</h2>"
        class="mb-4"
      >
        <RequiredText/>
        <b-form-group
          label-cols-sm="2"
          label-cols-lg="1"
          label-for="public"
        >
          <template v-slot:label>
            Public*
            <QuestionCircleTooltip id="public-question-tooltip-h5p"/>
            <b-tooltip target="public-question-tooltip-h5p"
                       delay="250"
                       triggers="hover focus"
            >
              Questions that are public can be used by any instructor. Questions that are not public are only
              accessible
              by you.
            </b-tooltip>
          </template>
          <b-form-row class="mt-2">
            <b-form-radio-group
              id="public"
              v-model="h5pImportCollectionForm.public"
            >
              <b-form-radio name="public" value="1">
                Yes
              </b-form-radio>
              <b-form-radio name="public" value="0">
                No
              </b-form-radio>
            </b-form-radio-group>
          </b-form-row>
        </b-form-group>
        <b-form-group
          label-cols-sm="2"
          label-cols-lg="1"
          label="Level*"
          label-for="level"
        >
          <b-form-row class="mt-2">
            <b-form-radio-group
              id="level"
              v-model="h5pImportLevel"
              @change="questionsToImport= []"
            >
              <b-form-radio name="level" value="question">
                Question
              </b-form-radio>
              <b-form-radio name="level" value="collection">
                Collection
              </b-form-radio>
            </b-form-radio-group>
          </b-form-row>
        </b-form-group>
        <b-form-group
          v-if="importTemplate === 'h5p' && h5pImportLevel === 'collection'"
          label-cols-sm="2"
          label-cols-lg="1"
          label="Import to*"
          label-for="import_to"
        >
          <b-form-row>
            <b-col>
              <b-form-select
                id="import_to"
                v-model="h5pImportCollectionForm.import_to_course"
                style="width:400px"
                size="sm"
                :options="importToCourseOptions"
                class="mt-2"
                :class="{ 'is-invalid': h5pImportCollectionForm.errors.has('import_to_course') }"
                @change="h5pImportCollectionForm.errors.clear('assignment_template');h5pImportCollectionForm.errors.clear('import_to_course')"
              />
              <has-error :form="h5pImportCollectionForm" field="import_to_course"/>
            </b-col>

            <b-col v-if="importTemplate === 'h5p' && h5pImportCollectionForm.import_to_course !== 0">
              <div v-if="!assignmentTemplateOptions.length">
                <b-alert show variant="info">
                  Before you can import the questions into this course, please create at least one
                  <span class="d-inline-flex"><router-link :to="{name: 'assignments.templates'}">
                    assignment template
                  </router-link>.</span>
                </b-alert>
              </div>
              <div v-if="assignmentTemplateOptions.length">
                <b-form-select
                  id="assignment_template"
                  v-model="h5pImportCollectionForm.assignment_template"
                  style="width:400px"
                  size="sm"
                  class="mt-2"
                  :options="assignmentTemplateOptions"
                  :class="{ 'is-invalid': h5pImportCollectionForm.errors.has('assignment_template') }"
                  @change="h5pImportCollectionForm.errors.clear('assignment_template')"
                />
                <has-error :form="h5pImportCollectionForm" field="assignment_template"/>
              </div>
            </b-col>
          </b-form-row>
        </b-form-group>
        <b-form-group
          label-cols-sm="2"
          label-cols-lg="1"
          label-for="folder"
          label="Folder*"
        >
          <b-form-row>
            <SavedQuestionsFolders
              ref="bulkImportSavedQuestionsFolders"
              :key="`bulk-import-saved-questions-folder-${bulkImportSavedQuestionsKey}`"
              class="mt-2"
              :modal-id="'modal-for-bulk-import'"
              :type="'my_questions'"
              :folder-to-choose-from="'My Questions'"
              :question-source-is-my-favorites="false"
              :create-modal-add-saved-questions-folder="true"
              @savedQuestionsFolderSet="setMyCoursesFolder"
              @exportSavedQuestionsFolders="exportSavedQuestionsFolders"
            />
            <input type="hidden" class="form-control is-invalid">
            <span class="help-block invalid-feedback">
              {{ h5pImportCollectionForm.errors.get('folder_id') }}
            </span>
          </b-form-row>
        </b-form-group>
        <b-card-text>
          <div v-if="h5pImportLevel === 'question'">
            <b-form-group
              label-for="h5p_ids"
              label="H5P IDs*"
            >
              <b-form-textarea
                id="h5p_ids"
                v-model="h5pIds"
                placeholder="1, 2, 3..."
                tabindex="0"
              />
            </b-form-group>
            <b-button variant="primary"
                      :disabled="h5pIds === ''"
                      size="sm"
                      @click="importH5PQuestions"
            >
              Import
            </b-button>
          </div>
          <div v-if="h5pImportLevel === 'collection'">
            <b-form-group>
              <b-form-row>
                <span style="margin-right:10px">Collection*</span>
                <b-form-select
                  id="collection"
                  v-model="h5pImportCollectionForm.collection"
                  style="width: 500px"
                  size="sm"
                  :options="h5pCollectionOptions"
                  :class="{ 'is-invalid': h5pImportCollectionForm.errors.has('collection') }"
                  @change="h5pImportCollectionForm.errors.clear('collection')"
                />
                <has-error :form="h5pImportCollectionForm" field="collection"/>
                <b-alert v-if="!h5pCollectionOptions.length" show variant="info">
                  There are no available H5P collections.
                </b-alert>
              </b-form-row>
            </b-form-group>
            <b-button variant="primary"
                      :disabled="h5pImportCollectionForm.collection === null"
                      size="sm"
                      @click="validateH5PCollectionImport"
            >
              Import
            </b-button>
          </div>
        </b-card-text>
      </b-card>
    </div>
    <div v-if="['advanced','webwork','qti','bow_tie','case_study_notes'].includes(importTemplate)">
      <b-card
        :header-html="getBulkImportHtml()"
        class="mb-4"
      >
        <div>
          <b-button v-if="['advanced','webwork'].includes(importTemplate)"
                    variant="secondary"
                    size="sm"
                    @click="$bvModal.show('modal-bulk-upload-instructions')"
          >
            Instructions
          </b-button>
          <ConsultInsight v-show="importTemplate === 'advanced'"
                          :url="'https://commons.libretexts.org/insight/mom-to-adapt'"
          />
        </div>
        <div v-if="importTemplate === 'qti' && qtiSource === 'canvas'">
          <p>Using the QTI importer, you can import questions directly from Canvas. We currently support:</p>
          <ul>
            <li>True/False</li>
            <li>Multiple Choice</li>
            <li>Multiple Answer</li>
            <li>Fill in the blank</li>
            <li>Fill in multiple blanks</li>
            <li>Multiple dropdown</li>
            <li>Essay</li>
            <li>Text only</li>
          </ul>
        </div>
        <RequiredText v-if="importTemplate === 'qti'" class="pt-2"/>

        <div v-if="importTemplate === 'qti'">
          <b-form-group
            v-if="importTemplate === 'qti'"
            v-show="false"
            class="mt-2"
            label-cols-sm="2"
            label-cols-lg="1"
            label="Source*"
            label-for="qti_source"
          >
            <b-form-radio-group
              id="qti_source"
              v-model="qtiSource"
              stacked
              required
            >
              <b-form-radio name="qti_source" value="canvas">
                Canvas
                <QuestionCircleTooltip id="canvas_tooltip"/>
                <b-tooltip target="canvas_tooltip"
                           delay="250"
                           triggers="hover focus"
                >
                  From Canvas, export a single quiz or multiple quizzes. ADAPT can import multiple choice, fill in the
                  blank,
                  fill in multiple blanks, and multiple dropdown questions.
                </b-tooltip>
              </b-form-radio>
              <b-form-radio name="qti_source" value="v2.2">
                QTI v2.2
                <QuestionCircleTooltip id="v2.2_tooltip"/>
                <b-tooltip target="v2.2_tooltip"
                           delay="250"
                           triggers="hover focus"
                >
                  From a QTI v2.2 file, ADAPT can import multiple choice and true/false questions.
                </b-tooltip>
              </b-form-radio>
            </b-form-radio-group>
          </b-form-group>
          <b-form-group
            v-if="importTemplate === 'qti' && qtiSource === 'canvas'"
            label-cols-sm="2"
            label-cols-lg="1"
            label-for="public"
          >
            <template v-slot:label>
              Public*
              <QuestionCircleTooltip id="public-question-tooltip-qti"/>
              <b-tooltip target="public-question-tooltip-qti"
                         delay="250"
                         triggers="hover focus"
              >
                Questions that are public can be used by any instructor. Questions that are not public are only
                accessible
                by you.
              </b-tooltip>
            </template>
            <b-form-row class="mt-2">
              <b-form-radio-group
                id="public"
                v-model="publicQuestion"
              >
                <b-form-radio name="public" value="1">
                  Yes
                </b-form-radio>
                <b-form-radio name="public" value="0">
                  No
                </b-form-radio>
              </b-form-radio-group>
            </b-form-row>
          </b-form-group>
          <b-form-group
            v-if="importTemplate === 'qti' && qtiSource === 'canvas'"
            label-cols-sm="2"
            label-cols-lg="1"
            label="Import to*"
            label-for="import_to"
          >
            <b-form-row>
              <b-col>
                <b-form-select
                  id="import_to"
                  v-model="importToCourse"
                  size="sm"
                  class="mt-2"
                  style="width:400px"
                  :options="importToCourseOptions"
                />
                <input type="hidden" class="form-control is-invalid">
                <span v-if="importTemplate === 'qti'" class="help-block invalid-feedback">
                  {{ qtiUploadFormErrors.import_to_course }}
                </span>
              </b-col>
              <b-col v-if="importTemplate === 'qti' && importToCourse !== 0">
                <div v-if="!assignmentTemplateOptions.length">
                  <b-alert show variant="info">
                    Before you can import the questions into this course, please create at least one
                    <span class="d-inline-flex"><router-link :to="{name: 'assignments.templates'}">
                      assignment template
                    </router-link>.</span>
                  </b-alert>
                </div>
                <div v-if="assignmentTemplateOptions.length">
                  <div class="d-flex align-items-center">
                    <b-form-select
                      id="assignment_template"
                      v-model="assignmentTemplate"
                      style="width:400px"
                      class="mt-2"
                      size="sm"
                      :options="assignmentTemplateOptions"
                    />
                    <QuestionCircleTooltip id="assignment-template-tooltip" class="ml-2 mt-2" />
                    <b-tooltip target="assignment-template-tooltip"
                               delay="250"
                               triggers="hover focus"
                    >
                      Choose from one one of your assigment templates and ADAPT will create an assignment
                      using the name from Canvas and the assignment properties from your template.
                    </b-tooltip>
                  </div>

                  <input type="hidden" class="form-control is-invalid">
                  <span v-if="importTemplate === 'qti'" class="help-block invalid-feedback">
    {{ qtiUploadFormErrors.assignment_template }}
  </span>
                </div>
              </b-col>
            </b-form-row>
          </b-form-group>

          <b-form-group
            label-cols-sm="2"
            label-cols-lg="1"
            label-for="folder"
            label="Folder*"
          >
            <b-form-row>
              <SavedQuestionsFolders
                ref="bulkImportSavedQuestionsFolders"
                :key="`qti-saved-questions-folder-${bulkImportSavedQuestionsKey}`"
                class="mt-2"
                :modal-id="'modal-for-qti-import'"
                :type="'my_questions'"
                :folder-to-choose-from="'My Questions'"
                :question-source-is-my-favorites="false"
                :create-modal-add-saved-questions-folder="true"
                @savedQuestionsFolderSet="setMyCoursesFolder"
                @exportSavedQuestionsFolders="exportSavedQuestionsFolders"
              />
              <input type="hidden" class="form-control is-invalid">
              <span v-if="importTemplate === 'qti'" class="help-block invalid-feedback">
                {{ qtiUploadFormErrors.folder_id }}
              </span>
            </b-form-row>
          </b-form-group>

          <b-form-group
            label-cols-sm="2"
            label-cols-lg="1"
            label-for="author"
            label="Author*"
          >
            <b-form-row>
              <b-form-input
                id="author"
                v-model="author"
                style="width:250px"
              />
              <input type="hidden" class="form-control is-invalid">
              <span v-if="importTemplate === 'qti'" class="help-block invalid-feedback">
                {{ qtiUploadFormErrors.author }}
              </span>
            </b-form-row>
          </b-form-group>
          <b-form-group
            label-cols-sm="2"
            label-cols-lg="1"
            label-for="license"
            label="License*"
          >
            <b-form-row>
              <b-form-select v-model="license"
                             style="width:200px"
                             title="license"
                             size="sm"
                             class="mt-2  mr-2"
                             :options="licenseOptions"
                             @change="licenseVersion = updateLicenseVersions(license)"
              />
              <input type="hidden" class="form-control is-invalid">
              <span v-if="importTemplate === 'qti'" class="help-block invalid-feedback">
                {{ qtiUploadFormErrors.license }}
              </span>
            </b-form-row>
          </b-form-group>
          <b-form-group
            v-if="licenseVersionOptions.length"
            label-cols-sm="2"
            label-cols-lg="1"
            label-for="license_version"
            label="License Version*"
          >
            <b-form-row>
              <b-form-select v-model="licenseVersion"
                             style="width:100px"
                             title="license version"
                             required
                             size="sm"
                             class="mt-2"
                             :options="licenseVersionOptions"
              />
            </b-form-row>
          </b-form-group>
          <b-form-group>
            <b-form-row>
              <span class="mr-2">Source URL</span>
              <b-form-input
                id="author"
                v-model="sourceURL"
                style="width:800px"
              />
            </b-form-row>
          </b-form-group>
        </div>
        <SavedQuestionsFolders
          v-if="['advanced','h5p'].includes(importTemplate)"
          v-show="false"
          ref="bulkImportSavedQuestionsFoldersmyopenmath"
          class="mt-2"
          :type="'my_questions'"
          :folder-to-choose-from="'My Questions'"
          :question-source-is-my-favorites="false"
        />
        <b-card-text>
          <b-form-group
            v-show="['advanced','webwork'].includes(importTemplate)"
            label-cols-sm="3"
            label-cols-lg="2"
            label="Import questions to*"
            label-for="import_questions_to"
          >
            <b-form-select
              id="import_questions_to"
              v-model="importToCourse"
              style="width:400px"
              class="mt-1"
              size="sm"
              :options="importToCourseOptions"
            />
          </b-form-group>
          <div v-show="importTemplate === 'case_study_notes'">
            <b-alert show variant="info">
              Importing Case Study Notes will overwrite the current notes. All notes will be set to the first question.
            </b-alert>
          </div>
          <b-button
            v-if="['advanced','webwork','bow_tie','case_study_notes'].includes(importTemplate)"
            variant="success"
            size="sm"
            @click="downloadCSVStructure"
          >
            Download {{ getImportTemplateName() }} Import Template
          </b-button>

          <b-container v-if="importTemplate === 'qti'" class="mt-2">
            <b-row>
              <file-upload
                ref="upload"
                :key="importTemplate"
                v-model="files"
                class="btn btn-success btn-sm"
                accept=".zip"
                put-action="/put.method"
                @input-file="inputFile"
                @input-filter="inputFilter"
              >
                Select QTI .zip file
              </file-upload>
            </b-row>
          </b-container>
          <b-row v-if="['advanced','webwork','qti','bow_tie','case_study_notes'].includes(importTemplate)"
                 class="upload mt-3 ml-1"
          >
            <div v-if="files.length && (preSignedURL !== '')">
              <span v-for="file in files" :key="file.id">File to upload:
                <span :class="file.success ? 'text-success font-weight-bold' : ''">{{
                    file.name
                  }}</span> -
                <span>{{ formatFileSize(file.size) }} </span>
                <span v-if="file.size > 10000000">Note: large files may take a minute to upload.</span>
                <span v-if="file.error" class="text-danger">Error: {{ file.error }}</span>
                <b-button v-if="!processingFile && (preSignedURL !== '') && (!$refs.upload || !$refs.upload.active)"
                          variant="info"
                          size="sm"
                          style="vertical-align: top"
                          :disabled="disableQtiStartUpload"
                          @click.prevent="initStartUpload"
                >
                  Import
                </b-button>
                <span v-else-if="file.active" class="ml-2 text-info">
                  <b-spinner small type="grow"/>
                  Uploading File...
                </span>
                <span v-if="processingFile && !file.active" :class="qtiQueuedError ? 'text-danger' : 'text-info'">
                  <b-spinner
                    small
                    type="grow"
                  />
                  {{ processingFileMessage }}
                </span>
              </span>
            </div>
          </b-row>
          <b-row v-if="['advanced','webwork','bow_tie','case_study_notes'].includes(importTemplate)">
            <b-col cols="6">
              <b-form-file
                v-model="bulkImporterFileForm.bulkImportFile"
                class="mb-2"
                placeholder="Choose a file or drop it here..."
                drop-placeholder="Drop file here..."
              />
              <div v-if="uploading">
                <b-spinner small type="grow"/>
                Uploading file...
              </div>
              <input type="hidden" class="form-control is-invalid">
              <div class="help-block invalid-feedback">
                {{ bulkImporterFileForm.errors.get('bulk_import_file') }}
              </div>
            </b-col>
            <b-col>
              <b-button variant="info"
                        :disabled="disableImport"
                        @click="uploadBulkImportFile"
              >
                Import
              </b-button>
            </b-col>
          </b-row>
        </b-card-text>
      </b-card>

      <div v-if="errorMessages.length" class="text-danger">
        Please fix the following errors:
        <ul v-for="errorMessage in errorMessages" :key="errorMessage">
          <li>{{ errorMessage }}</li>
        </ul>
      </div>
    </div>

    <div v-if="!['flashcard'].includes(importTemplate)" style="min-height:200px">
      <b-container v-if="questionsToImport.length" fluid>
        <h2 class="h5">
          Import Summary
        </h2>
        <table class="table table-striped pb-3" style="width:auto">
          <thead>
          <tr>
            <th scope="col" style="width:200px">
              Import Status
            </th>
            <th scope="col" style="width:200px">
              Count
            </th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="item in questionsToImportSummary" :key="item.key">
            <td style="width:200px">
                <span v-if="parseInt(item.total) >0">
                  <a href="" @click.prevent="filter = item.key">{{
                      item.key
                    }}</a>
                </span>
              <span v-if="parseInt(item.total) === 0">{{ item.key }}</span>
            </td>
            <td> {{ item.total }}</td>
          </tr>
          </tbody>
        </table>

        <h2 class="h5">
          Questions to Import
        </h2>
        <b-table v-if="importTemplate === 'qti'"
                 aria-label="Qti questions to import"
                 striped
                 hover
                 responsive
                 :filter="filter"
                 :no-border-collapse="true"
                 :items="questionsToImport"
        >
          <template v-slot:cell(import_status)="data">
            <span v-html="data.item.import_status"/>
          </template>
        </b-table>
        <b-table
          v-if="['advanced','webwork','h5p','bow_tie'].includes(importTemplate)"
          aria-label="QuestionsToImport"
          striped
          hover
          responsive
          :no-border-collapse="true"
          :fields="fields"
          :filter="filter"
          :items="questionsToImport"
        >
          <template v-slot:cell(import_status)="data">
            <span v-html="data.item.import_status"/>
          </template>
          <template v-slot:cell(title)="data">
            <span v-if="data.item.url">
              <a :href="data.item.url" target="_blank">{{ data.item.title }}</a></span>
            <span v-if="!data.item.url">{{ data.item.title }}</span>
          </template>
          <template v-slot:cell(Title*)="data">
            <span v-if="data.item.url">
              <a :href="data.item.url" target="_blank">{{ data.item['Title*'] }}</a></span>
            <span v-if="!data.item.url">{{ data.item['Title*'] }}</span>
          </template>
          <template v-if="importTemplate === 'advanced'" v-slot:cell(Technology)="data">
            {{ data.item.Technology === '' ? 'text' : data.item.Technology }}
          </template>
        </b-table>
      </b-container>
    </div>
  </div>
</template>

<script>
import { doCopy } from '~/helpers/Copy'
import { downloadFile } from '~/helpers/DownloadFiles'
import axios from 'axios'
import Form from 'vform'
import SavedQuestionsFolders from '~/components/SavedQuestionsFolders'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import AllFormErrors from '~/components/AllFormErrors'
import Vue from 'vue'
import { formatFileSize } from '~/helpers/UploadFiles'
import { defaultLicenseVersionOptions, licenseOptions, updateLicenseVersions } from '~/helpers/Licenses'
import { getAssignmentTemplateOptions } from '~/helpers/AssignmentProperties'
import { mapGetters } from 'vuex'
import { v4 as uuidv4 } from 'uuid'
import ConsultInsight from '../ConsultInsight.vue'

const VueUploadComponent = require('vue-upload-component')
Vue.component('file-upload', VueUploadComponent)

let h5pFields = [
  {
    key: 'id',
    label: 'ID'
  },
  'title',
  {
    key: 'tags',
    formatter: value => {
      if (value === 'N/A') {
        return value
      }
      return value.length
        ? value.join(', ')
        : 'none'
    }
  },
  'import_status'
]

export default {
  name: 'BulkImporter',
  components: {
    ConsultInsight,
    SavedQuestionsFolders,
    FontAwesomeIcon,
    AllFormErrors,
    FileUpload: VueUploadComponent
  },
  props: {
    initImportTemplate: {
      type: String,
      default: ''
    },
    assignmentId: {
      type: Number,
      default: 0
    }
  },
  data: () => ({
    // ── Flashcard-specific ────────────────────────────────────────────────
    flashcardImportFile: null,
    flashcardUploading: false,
    flashcardImportCourseId: null,
    flashcardImportAssignmentId: null,
    flashcardCourseOptions: [{ value: null, text: 'No specific course; import only to My Questions' }],
    flashcardAssignmentOptions: [{ value: null, text: 'Please choose an assignment' }],
    flashcardTableFields: [
      { key: 'Title*', label: 'Title' },
      { key: 'Front*', label: 'Front' },
      { key: 'Back*', label: 'Back' },
      { key: 'Hint', label: 'Hint' },
      { key: 'Tags', label: 'Tags' },
      'import_status'
    ],
    // ─────────────────────────────────────────────────────────────────────
    h5pCollectionOptions: [],
    h5pImportLevel: 'question',
    h5pImportCollectionForm: new Form({
      public: 0,
      import_to_course: 0,
      assignment_template: null,
      collection: null
    }),
    publicQuestion: 0,
    assignmentTemplate: null,
    assignmentTemplateOptions: [],
    qtiSource: 'canvas',
    qtiQueuedError: false,
    processingFileMessage: '',
    qtiQueuedJobCompleted: false,
    license: null,
    licenseVersion: '',
    licenseOptions: licenseOptions,
    defaultLicenseVersionOptions: defaultLicenseVersionOptions,
    licenseVersionOptions: [],
    questionsToImportSummary: {},
    author: '',
    sourceURL: '',
    qtiUploadFormErrors: {
      'author': '',
      'folder_id': '',
      'license': ''
    },
    disableQtiStartUpload: false,
    qtiFile: '',
    processingFile: false,
    s3Key: '',
    originalFilename: '',
    handledOK: false,
    preSignedURL: '',
    allFormErrors: [],
    files: [],
    importToCourseOptions: [],
    importToCourse: 0,
    copyIcon: faCopy,
    assignments: {},
    course: null,
    myCoursesOptions: [],
    bulkImportSavedQuestionsKey: 0,
    myQuestionsFolders: [],
    folderId: 0,
    disableImport: false,
    filter: null,
    questionsToImportValidationErrors: [],
    fields: h5pFields,
    h5pImports: [],
    h5pIds: '',
    importTemplate: 'h5p',
    currentPage: 1,
    pageOptions: [10, 50, 100, 500, { value: 10000, text: 'Show All' }],
    perPage: 10,
    questionsToImport: [],
    errorMessages: [],
    validLicenses: '',
    bulkImportStopped: false,
    uploading: false,
    bulkImporterFileForm: new Form({
      bulkImportFile: []
    }),
    uploadFileForm: new Form({
      type: 'qti'
    })
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  watch: {
    qtiSource (qtiSource) {
      if (qtiSource === 'v2.2') {
        this.importToCourse = 0
        this.assignmentTemplate = null
      }
    }
  },
  created () {
    this.getAssignmentTemplateOptions = getAssignmentTemplateOptions
  },
  mounted () {
    if (this.initImportTemplate === 'case_study_notes') {
      this.importTemplate = 'case_study_notes'
    }
    if (![2, 5].includes(this.user.role)) {
      return false
    }
    this.updateLicenseVersions = updateLicenseVersions
    this.author = this.user.first_name + ' ' + this.user.last_name
    this.doCopy = doCopy
    this.formatFileSize = formatFileSize
    this.bulkImportSavedQuestionsKey++
    this.getValidLicenses()
    this.getMyCourses()
    this.getH5PCollections()
    this.getAssignmentTemplateOptions()
    this.getFlashcardCourses()
  },
  methods: {
    // ── Flashcard import methods ──────────────────────────────────────────

    async getFlashcardCourses () {
      try {
        const { data } = await axios.get('/api/courses')
        if (data.type !== 'success') return
        if (data.courses) {
          for (const course of data.courses) {
            this.flashcardCourseOptions.push({ value: course.id, text: course.name })
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },

    async onFlashcardCourseChange (courseId) {
      this.flashcardImportAssignmentId = null
      this.flashcardAssignmentOptions = [{ value: null, text: 'Please choose an assignment' }]
      if (!courseId) return
      try {
        const { data } = await axios.get(`/api/assignments/courses/${courseId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        for (const assignment of data.assignments) {
          this.flashcardAssignmentOptions.push({ value: assignment.id, text: assignment.name })
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },

    async uploadFlashcardImportFile () {
      if (!this.flashcardImportFile) {
        this.$noty.info('Please select a CSV file.')
        return
      }
      this.flashcardUploading = true
      this.errorMessages = []
      this.questionsToImport = []
      this.questionsToImportSummary = []

      try {
        // Step 1: validate the CSV server-side
        const formData = new FormData()
        formData.append('bulk_import_file', this.flashcardImportFile)
        formData.append('_method', 'put')
        formData.append('import_template', 'flashcard')
        formData.append('course_id', '')

        const { data } = await axios.post('/api/questions/validate-bulk-import', formData)

        if (data.type !== 'success') {
          this.errorMessages = Array.isArray(data.message) ? data.message : [data.message]
          this.flashcardUploading = false
          return
        }

        // Step 2: import row by row
        await this.importFlashcardQuestions(data.items_to_import)
      } catch (error) {
        this.$noty.error(error.message)
      }

      this.flashcardUploading = false
      this.flashcardImportFile = null
    },

    async importFlashcardQuestions (itemsToImport) {
      this.questionsToImport = itemsToImport
      this.questionsToImportSummary = [
        { key: 'Pending', total: itemsToImport.length },
        { key: 'Success', total: 0 },
        { key: 'Error', total: 0 }
      ]

      for (let i = 0; i < this.questionsToImport.length; i++) {
        const row = this.questionsToImport[i]

        // Build qti_json for this card
        const card = {
          frontType: 'text_only',
          backType: 'text_only',
          frontTTSLanguage: row['Front Text Language*'],
          backTTSLanguage: row['Back Text Language*'],
          term: row['Front*'],
          answer: row['Back*']
        }
        const hint = row['Hint'] ? row['Hint'].trim() : null
        if (hint) {
          card.hint = hint
        }
        const qtiJson = JSON.stringify({
          questionType: 'flashcard',
          card
        })

        try {
          const payload = {
            title: row['Title*'],
            technology: 'qti',
            question_type: 'assessment',
            qti_json: qtiJson,
            public: row['Public*'],
            author: row['Author*'],
            license: row['License*'],
            license_version: '',
            folder_id: row['folder_id'],
            tags: row['Tags'] ? row['Tags'] : [],
            open_ended_submission_type: '0',
            source_url_required: false,
            bulk_upload_into_assignment: true,
            hint: hint,
            text_question: null,
            answer_html: null,
            solution_html: null,
            notes: null,
            non_technology_text: null,
            technology_id: null
          }

          // Optionally attach to an assignment
          if (this.flashcardImportAssignmentId) {
            payload.assignment_id = this.flashcardImportAssignmentId
          }

          const { data } = await axios.post('/api/questions', payload)

          if (data.type === 'success') {
            row.import_status = '<span class="text-success">Success</span>'
            this.questionsToImportSummary.find(s => s.key === 'Success').total++
          } else {
            row.import_status = `<span class="text-danger">Error: ${data.message}</span>`
            this.questionsToImportSummary.find(s => s.key === 'Error').total++
          }
        } catch (error) {
          let msg = error.message
          if (error.response && error.response.status === 422) {
            const errors = Object.values(error.response.data.errors).flat()
            msg = errors.join('; ')
          }
          row.import_status = `<span class="text-danger">Error: ${msg}</span>`
          this.questionsToImportSummary.find(s => s.key === 'Error').total++
        }

        this.questionsToImportSummary.find(s => s.key === 'Pending').total--
        // Force Vue reactivity on the row
        this.$set(this.questionsToImport, i, { ...row })
      }

      this.$noty.info('Flashcard import complete.')
    },

    // ── Existing methods (unchanged) ─────────────────────────────────────

    getImportTemplateName () {
      switch (this.importTemplate) {
        case ('webwork'):
          return 'WebWork'
        case ('advanced'):
          return 'MyOpenMath'
        case ('bow_tie'):
          return 'Bowtie'
        case ('case_study_notes'):
          return 'Case Study Notes'
        default:
          return 'Not a valid template name'
      }
    },
    async getH5PCollections () {
      try {
        const { data } = await axios.get('/api/h5p-collections')
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        if (data.collections.length) {
          this.h5pCollectionOptions = [{ value: null, text: 'Please choose a collection' }]
        }
        for (let i = 0; i < data.collections.length; i++) {
          let collection = data.collections[i]
          this.h5pCollectionOptions.push({
            value: +collection.cid,
            text: `${collection.title} (${collection.author_name})`
          })
        }
        console.log(this.h5pCollectionOptions)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    initStartUpload () {
      this.processingFileMessage = ''
      this.qtiQueuedError = false
      let errors = false
      this.qtiUploadFormErrors.folder_id = ''
      this.qtiUploadFormErrors.author = ''
      this.qtiUploadFormErrors.license = ''
      this.qtiUploadFormErrors.assignment_template = ''
      this.allFormErrors = []
      if (this.importToCourse && !this.assignmentTemplate) {
        this.qtiUploadFormErrors.assignment_template = 'Please select an assignment template.'
        this.allFormErrors.push(this.qtiUploadFormErrors.assignment_template)
        errors = true
      }
      if (!this.folderId) {
        this.qtiUploadFormErrors.folder_id = 'Please select a folder.'
        this.allFormErrors.push(this.qtiUploadFormErrors.folder_id)
        errors = true
      }
      if (!this.author) {
        this.qtiUploadFormErrors.author = 'An author is required.'
        this.allFormErrors.push(this.qtiUploadFormErrors.author)
        errors = true
      }
      if (!this.license) {
        this.qtiUploadFormErrors.license = 'A license is required.'
        this.allFormErrors.push(this.qtiUploadFormErrors.license)
        errors = true
      }
      if (errors) {
        this.$bvModal.show('modal-form-errors-file-upload')
        return false
      }
      this.$refs.upload.active = true
    },
    async inputFilter (newFile, oldFile, prevent) {
      this.uploadFileForm.errors.clear()
      this.errorMessages = []
      this.qtiQueuedError = false
      if (newFile && !oldFile) {
        // Filter non-image file
        if (parseInt(newFile.size) > 30000000) {
          let message = '30 MB max allowed.  Your file is too large.  '
          this.uploadFileForm.errors.set('qti', message)
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.uploadFileForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-file-upload')
          return prevent()
        }

        let validExtension
        validExtension = /\.(zip)$/i.test(newFile.name)
        if (!validExtension) {
          this.uploadFileForm.errors.set('qti', 'Zip files are the only ones accepted')
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.uploadFileForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-file-upload')
          return prevent()
        } else {
          try {
            this.preSignedURL = ''
            let uploadFileData = {
              upload_file_type: 'qti',
              file_name: newFile.name
            }
            const { data } = await axios.post('/api/s3/pre-signed-url', uploadFileData)
            if (data.type === 'error') {
              this.$noty.error(data.message)
              return false
            }
            this.preSignedURL = data.preSignedURL
            newFile.putAction = this.preSignedURL

            this.s3Key = data.s3_key
            this.originalFilename = newFile.name
            this.qtiFile = data.qti_file
            this.handledOK = false
          } catch (error) {
            this.$noty.error(error.message)
            return false
          }
        }
      }

      // Create a blob field
      newFile.blob = ''
      let URL = window.URL || window.webkitURL
      if (URL && URL.createObjectURL) {
        newFile.blob = URL.createObjectURL(newFile.file)
      }
      console.log(newFile.blob)
    },
    inputFile (newFile, oldFile) {
      if (newFile && oldFile && !newFile.active && oldFile.active) {
        // Get response data
        if (newFile.xhr) {
          //  Get the response status code
          console.log('status', newFile.xhr.status)
          if (newFile.xhr.status === 200) {
            if (!this.handledOK) {
              this.handledOK = true
              console.log(this.handledOK)
              this.disableQtiStartUpload = true
              this.handleOK()
            }
          } else {
            this.$noty.error('We were not able to save your file to our server.  Please try again or contact us if the problem persists.')
          }
        } else {
          this.$noty.error('We were not able to save your file to our server.  Please try again or contact us if the problem persists.')
        }
      }
    },
    async validateQtiImport (qtiFile) {
      const { data } = await axios.post(`/api/questions/validate-bulk-import`,
        {
          import_template: 'qti',
          qti_source: this.qtiSource,
          public: this.publicQuestion,
          qti_file: qtiFile,
          author: this.author,
          folder_id: this.folderId,
          license: this.license,
          import_to_course: this.importToCourse,
          assignment_template: this.assignmentTemplate,
          license_version: this.licenseVersion,
          source: this.qtiSource,
          _method: 'put'
        })
      return data
    },
    async importQtiQuestions (qtiJobId) {
      this.questionsToImportSummary = [
        {
          key: 'Pending',
          total: this.questionsToImport.length
        },
        {
          key: 'Success',
          total: 0
        },
        {
          key: 'Error',
          total: 0
        }]
      for (let i = 0; i < this.questionsToImport.length; i++) {
        let questionToImport = this.questionsToImport[i]
        try {
          const { data } = await axios.post(`/api/qti-import`,
            {
              qti_job_id: qtiJobId,
              identifier: questionToImport.identifier,
              author: this.author,
              folder_id: this.folderId,
              license: this.license,
              license_version: this.licenseVersion,
              source_url: this.sourceURL
            })
          questionToImport.import_status = data.type === 'success'
            ? '<span class="text-success">Success</span>'
            : `<span class="text-danger">Error: ${data.message}</span>`
          if (data.type === 'success') {
            questionToImport.title = data.title
          }
          data.type === 'success'
            ? this.questionsToImportSummary.find(summary => summary.key === 'Success').total++
            : this.questionsToImportSummary.find(summary => summary.key === 'Error').total++
        } catch (error) {
          questionToImport.import_status = `<span class="text-danger">Error: ${error.message}</span>`
          this.questionsToImportSummary.find(summary => summary.key === 'Error').total++
        }
        this.questionsToImportSummary.find(summary => summary.key === 'Pending').total--
      }
      this.files = []
    },
    async handleOK () {
      this.uploadFileForm.errors.clear('qti')
      this.processingFile = true
      try {
        let validated = await this.validateQtiImport(this.qtiFile)
        if (validated.type !== 'success') {
          if (validated.message.form_errors) {
            this.qtiUploadFormErrors.author = validated.message.form_errors.author
            this.qtiUploadFormErrors.folder_id = validated.message.form_errors.folder_id
            this.qtiUploadFormErrors.license = validated.message.form_errors.license
            this.qtiUploadFormErrors.assignment_template = validated.message.form_errors.assignment_template
            this.qtiUploadFormErrors.import_to_course = validated.message.form_errors.import_to_course
          } else if (validated.message) {
            this.$noty.error(validated.message)
          }
          this.processingFile = false
          this.files = []
          this.disableQtiStartUpload = false
          return false
        }
        this.qtiQueuedJobCompleted = false
        await this.pollQtiJobStatus(validated.qti_job_id)
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processingFile = false
      this.disableQtiStartUpload = false
      this.qtiQueuedJobCompleted = false
    },
    async getUpdatedQtiQueuedJobStatus (qtiJobId) {
      const { data } = await axios.post('/api/qti-job/status', { qti_job_id: qtiJobId })
      this.processingFileMessage = data.message
      if (data.type === 'success') {
        switch (data.status) {
          case ('completed'):
            this.qtiQueuedJobCompleted = true
            this.questionsToImport = data.questions_to_import
            await this.importQtiQuestions(qtiJobId)
            break
          case ('error'):
            this.$noty.error(data.message)
            this.qtiQueuedError = true
            this.qtiQueuedJobCompleted = true
        }
      } else {
        this.qtiQueuedJobCompleted = true
        this.qtiQueuedError = true
      }
    },
    sleep (ms) {
      return new Promise(resolve => setTimeout(resolve, ms))
    },
    async pollQtiJobStatus (qtiJobId) {
      while (!this.qtiQueuedJobCompleted) {
        await this.getUpdatedQtiQueuedJobStatus(qtiJobId)
        if (!this.qtiQueuedJobCompleted) {
          await this.sleep(3000)
        }
      }
    },
    async getAssignmentsAndTopicsByCourse (course) {
      try {
        const { data } = await axios.get(`/api/assignments/courses/${course}`)
        console.log(data)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.assignments = data.assignments
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getMyCourses () {
      try {
        this.importToCourseOptions = [{ value: 0, text: `No specific course; import only to My Questions` }]
        this.myCoursesOptions = [{ value: null, text: `Please choose a course` }]
        const { data } = await axios.get('/api/courses')
        console.log(data)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        if (data.courses) {
          for (let i = 0; i < data.courses.length; i++) {
            let course = data.courses[i]
            this.myCoursesOptions.push({ value: course.id, text: course.name })
            this.importToCourseOptions.push({ value: course.id, text: course.name })
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    exportSavedQuestionsFolders (savedQuestionsFolders) {
      this.myQuestionsFolders = savedQuestionsFolders.filter(folder => folder.value)
    },
    setMyCoursesFolder (myCoursesFolder) {
      this.folderId = myCoursesFolder
    },
    getBulkImportHtml () {
      if (this.importTemplate === 'qti') {
        return `<h2 class="h7">QTI Importer</h2>`
      } else {
        let type = this.getImportTemplateName()
        let downloadText = this.importTemplate !== 'case_study_notes' ? 'Download' : ''
        return `<h2 class="h7">${downloadText} ${type} Import Template</h2>`
      }
    },
    setQuestionsToImport (type) {
      this.questionsToImport = []
      this.errorMessages = []
      switch (type) {
        case ('h5p'):
          this.fields = h5pFields
          break
        case ('flashcard'):
          // fields handled by flashcardTableFields
          break
        case ('webwork'):
          this.fields = [
            {
              key: 'row',
              label: 'CSV Row',
              isRowHeader: true
            },
            {
              key: 'Title*',
              label: 'Title'
            },
            {
              key: 'File Path*',
              label: 'File Path'
            },
            {
              key: 'Tags',
              formatter: value => {
                return value.length
                  ? value.join(', ')
                  : 'none'
              }
            },
            'import_status'
          ]
          break
        case ('advanced'):
          this.fields = [
            {
              key: 'row',
              isRowHeader: true
            },
            {
              key: 'Title*',
              label: 'Title'
            },
            {
              key: 'Tags',
              formatter: value => {
                return value.length
                  ? value.join(', ')
                  : 'none'
              }
            },
            'import_status'
          ]
          break
        case ('qti'):
          this.fields = [{
            key: 'file',
            isRowHeader: true
          },
            'import_status']
          break
        case ('bow_tie'):
          this.fields = [{
            key: 'Title*',
            isRowHeader: true
          },
            'import_status']
          break
        default:
          alert('not valid type')
      }
    },
    async validateH5PCollectionImport () {
      this.h5pImportCollectionForm.errors.clear()
      try {
        this.h5pImportCollectionForm.folder_id = this.folderId
        if (!this.folderId) {
          this.$noty.info('Please choose a My Questions folder.')
          return false
        }
        const { data } = await this.h5pImportCollectionForm.post('/api/h5p-collections/validate-import')
        if (data.type !== 'success') {
          this.$noty.error(data.message)
        }
        this.questionsToImport = data.questions_to_import
        await this.importH5PQuestions()
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.h5pImportCollectionForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-h5p-collection-import')
        }
      }
    },
    async importH5PQuestions () {
      if (!this.folderId) {
        this.$noty.info('Please choose a My Questions folder.')
        return false
      }
      let h5pIds
      switch (this.h5pImportLevel) {
        case ('question'):
          h5pIds = this.h5pIds.split(',')
          this.questionsToImport = []
          for (let i = 0; i < h5pIds.length; i++) {
            this.questionsToImport.splice(i, 0, {
              id: h5pIds[i],
              import_status: 'Pending',
              title: 'N/A',
              author: 'N/A',
              tags: 'N/A'
            })
          }
          break
        case ('collection') :
          h5pIds = []
          for (let i = 0; i < this.questionsToImport.length; i++) {
            h5pIds.push(this.questionsToImport.id)
          }
          break
        default:
          this.$noty.error(`${this.h5pImportLevel} is not a valid level.`)
          return false
      }

      this.questionsToImportSummary = [
        {
          key: 'Pending',
          total: this.questionsToImport.length
        },
        {
          key: 'Success',
          total: 0
        },
        {
          key: 'Error',
          total: 0
        }]

      for (let i = 0; i < h5pIds.length; i++) {
        console.log(this.questionsToImport)
        let h5pId = this.questionsToImport[i].id
        let assignmentId = this.questionsToImport[i].assignment_id
        let questionToImport = {
          id: h5pIds[i],
          import_status: 'Pending',
          title: 'N/A',
          author: 'N/A',
          tags: 'N/A'
        }
        try {
          const { data } = await axios.post(`/api/questions/h5p/${h5pId}`, {
            assignment_id: assignmentId,
            folder_id: this.folderId
          })
          if (data.type === 'success') {
            questionToImport =
              {
                id: h5pId,
                tags: data.h5p.tags,
                title: data.h5p.title,
                url: data.h5p.url,
                author: data.h5p.author,
                import_status: '<span class="text-success">Success</span>'
              }
            this.questionsToImportSummary.find(summary => summary.key === 'Success').total++
          } else {
            questionToImport.import_status = `<span class="text-danger">Error: ${data.message}</span>`
            questionToImport =
              {
                id: h5pId,
                tags: data.h5p ? data.h5p.tags : 'N/A',
                title: data.h5p ? data.h5p.title : 'N/A',
                url: data.h5p ? data.h5p.url : '',
                author: data.h5p ? data.h5p.author : '',
                import_status: `<span class="text-danger">Error: ${data.message}</span>`
              }

            this.questionsToImportSummary.find(summary => summary.key === 'Error').total++
          }
        } catch (error) {
          questionToImport.import_status = `<span class="text-danger">Error: ${error.message}</span>`
          this.questionsToImportSummary.find(summary => summary.key === 'Error').total++
        }
        this.questionsToImport.splice(i, 1, questionToImport)
        this.questionsToImportSummary.find(summary => summary.key === 'Pending').total--
      }
      this.h5pIds = ''
    },
    async getValidLicenses () {
      try {
        const { data } = await axios.get('/api/questions/valid-licenses')
        this.validLicenses = data.licenses.join(', ')
      } catch (error) {
        this.$noty.error('We were not able to retrieve the list of valid licenses.')
      }
    },
    async downloadCSVStructure () {
      let url = `/api/questions/bulk-upload-template/${this.importTemplate}`
      if (this.importToCourse) {
        url += `/${this.importToCourse}`
      }
      downloadFile(url, [], `${this.importTemplate}-import-template.csv`, this.$noty)
    },
    stopBulkImport () {
      this.bulkImportStopped = true
    },
    async uploadBulkImportFile () {
      this.disableImport = true
      this.errorMessages = []
      try {
        if (this.uploading) {
          this.$noty.info('Please be patient while the file is uploading.')
          return false
        }

        this.uploading = true
        let formData = new FormData()
        formData.append('bulk_import_file', this.bulkImporterFileForm.bulkImportFile)
        formData.append('_method', 'put') // add this
        formData.append('import_template', this.importTemplate)
        formData.append('course_id', this.importToCourse)
        const { data } = await axios.post(`/api/questions/validate-bulk-import`, formData)
        if (data.type === 'success') {
          this.importTemplate === 'case_study_notes'
            ? await this.importCaseStudyNotes(data.items_to_import)
            : await this.importQuestions(data.items_to_import)
        } else {
          this.disableImport = false
          this.errorMessages = data.message
        }
      } catch (error) {
        if (error.message.includes('status code 413')) {
          error.message = 'The maximum size allowed is 10MB.'
        }
        this.$noty.error(error.message)
      }
      this.uploading = false
      this.disableImport = false
      this.bulkImporterFileForm.bulkImportFile = []
    },
    async importCaseStudyNotes (caseStudyNotesToImportArr) {
      let caseStudyNotesToImport = caseStudyNotesToImportArr[0] // assuming one row
      console.log(caseStudyNotesToImport)
      const possibleCaseStudyNotes = [
        {
          csvKey: 'History and Physical',
          adaptKey: 'history_and_physical'
        },
        {
          csvKey: 'History and Physical',
          adaptKey: 'history_and_physical'
        },
        {
          csvKey: 'Progress Notes',
          adaptKey: 'progress_notes'
        },
        {
          csvKey: 'Vital Signs',
          adaptKey: 'vital_signs'
        },
        {
          csvKey: 'Lab/Diagnostic Results',
          adaptKey: 'lab_results'
        },
        {
          csvKey: 'Provider Order',
          adaptKey: 'provider_order'
        },
        {
          csvKey: 'MAR',
          adaptKey: 'mar'
        },
        {
          csvKey: 'Handoff Report',
          adaptKey: 'handoff_report'
        }
      ]
      let caseStudyNotes = []
      for (let i = 0; i < possibleCaseStudyNotes.length; i++) {
        if (caseStudyNotesToImport[possibleCaseStudyNotes[i]['csvKey']]) {
          caseStudyNotes.push({
            type: possibleCaseStudyNotes[i]['adaptKey'],
            text: caseStudyNotesToImport[possibleCaseStudyNotes[i]['csvKey']]
          })
        }
      }
      let weight = caseStudyNotesToImport['Weight']
      console.log(caseStudyNotesToImport)
      const caseStudyNotesForm = new Form({
        assignment_id: this.assignmentId,
        common_question_text: caseStudyNotesToImport['Common Question Text'],
        bulk_import: true,
        case_study_notes: caseStudyNotes,
        patient_informations: {
          name: caseStudyNotesToImport['Name'],
          gender: caseStudyNotesToImport['Gender'],
          age: caseStudyNotesToImport['Age'],
          dob: caseStudyNotesToImport['DOB'],
          code_status: caseStudyNotesToImport['Code Status'].replace(/\s+/g, '_').toLowerCase(),
          allergies: caseStudyNotesToImport['Allergies'],
          weight: caseStudyNotesToImport['Weight'].replace(/\D/g, ''),
          weight_units: weight.replace(/[0-9]/g, ''),
          bmi: caseStudyNotesToImport['BMI'],
          updated_weight: null,
          updated_bmi: null,
          first_application_of_updated_information: null
        }
      })
      console.log(caseStudyNotesForm)
      const { data } = await caseStudyNotesForm.post('/api/case-study-notes/save-all')
      this.$noty[data.type](data.message)
      if (data.type === 'success') {
        this.$emit('reloadPage')
      }
      console.log(data)
    },
    async importQuestions (questionsToImport) {
      console.log(questionsToImport)
      this.questionsToImport = questionsToImport
      this.questionsToImportSummary = [
        {
          key: 'Pending',
          total: this.questionsToImport.length
        },
        {
          key: 'Success',
          total: 0
        },
        {
          key: 'Error',
          total: 0
        }]
      let questionForm
      for (let i = 0; i < this.questionsToImport.length; i++) {
        let question = this.questionsToImport[i]
        console.log(question)
        if (this.importTemplate === 'webwork') {
          question['Question Type*'] = 'assessment'
          question['Auto-Graded Technology'] = 'webwork'
          question['Technology ID/File Path'] = question['File Path*']
          question['Non-Technology Text'] = null
          question['A11Y Question'] = null
          question['Answer'] = null
          question['Solution'] = null
          question['Hint'] = null
        }
        try {
          questionForm = new Form({
            folder_id: question['folder_id'],
            question_type: question['Question Type*'],
            public: question['Public*'],
            title: question['Title*'],
            course_id: this.importToCourse,
            assignment: question['Assignment'],
            topic: question['Topic'],
            non_technology_text: question['Open-Ended Content'],
            technology: question['Auto-Graded Technology'],
            open_ended_submission_type: '0',
            technology_id: question['Technology ID/File Path'],
            text_question: question['Text Question'],
            a11y_question: question['A11Y Question'],
            answer_html: question['Answer'],
            solution_html: question['Solution'],
            hint: question['Hint'],
            author: question['Author*'],
            tags: question['Tags'],
            license: question['License*'],
            license_version: question['License Version'],
            source_url: question['Source URL'],
            bulk_upload_into_assignment: true,
            source_url_required: false
          })
          if (this.importTemplate === 'bow_tie') {
            questionForm.technology = 'qti'
            questionForm.nursing_question = true
            questionForm.question_type = 'assessment'
            questionForm.qti_prompt = question['Question Body*']
            questionForm.actions_to_take = []
            const actionsToTake = question['Actions to Take*'].split(',')
            for (let i = 0; i < actionsToTake.length; i++) {
              questionForm.actions_to_take.push({
                'identifier': uuidv4(),
                'value': actionsToTake[i],
                'correctResponse': i < 2
              })
            }
            questionForm.potential_conditions = []
            const potentialConditions = question['Potential Conditions*'].split(',')
            for (let i = 0; i < potentialConditions.length; i++) {
              questionForm.potential_conditions.push({
                'identifier': uuidv4(),
                'value': potentialConditions[i],
                'correctResponse': i < 1
              })
            }
            questionForm.parameters_to_monitor = []
            const parametersToMonitor = question['Parameters to Monitor*'].split(',')
            for (let i = 0; i < parametersToMonitor.length; i++) {
              questionForm.parameters_to_monitor.push({
                'identifier': uuidv4(),
                'value': parametersToMonitor[i],
                'correctResponse': i < 2
              })
            }
            questionForm.qti_json = JSON.stringify({
              questionType: 'bow_tie',
              actionsToTake: questionForm.actions_to_take,
              potentialConditions: questionForm.potential_conditions,
              parametersToMonitor: questionForm.parameters_to_monitor,
              feedback: {
                correct: question['Correct Feedback'],
                incorrect: question['Incorrect Feedback']
              },
              prompt: questionForm.qti_prompt
            })
          }
          console.log(questionForm)
          const { data } = await questionForm.post('/api/questions')
          if (data.type === 'success') {
            question.import_status = '<span class="text-success">Success</span>'
            question.url = data.url
          } else {
            question.import_status = `<span class="text-danger">Error: ${data.message}</span>`
          }
          data.type === 'success'
            ? this.questionsToImportSummary.find(summary => summary.key === 'Success').total++
            : this.questionsToImportSummary.find(summary => summary.key === 'Error').total++
        } catch (error) {
          if (error.message.includes('status code 422')) {
            let errors = questionForm.errors.flatten()
            let addS = errors.length > 1 ? '(s)' : ''
            question.import_status = `<div class="text-danger">Error${addS}</div><ul>`
            for (let i = 0; i < errors.length; i++) {
              question.import_status += `<li>${errors[i]}</li>`
            }
            question.import_status += '</ul>'
          } else {
            question.import_status = `<div class="text-danger">Error: ${error.message}</div>`
            this.questionsToImportSummary.find(summary => summary.key === 'Error').total++
          }
        }
        this.questionsToImportSummary.find(summary => summary.key === 'Pending').total--
      }
      this.$noty.info('Upload complete.')
    }
  }
}
</script>

<style scoped>
</style>
