/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `account_customizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_customizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `case_study_notes` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_customizations_user_id_foreign` (`user_id`),
  CONSTRAINT `account_customizations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `active_caches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `active_caches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adapt_mass_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `adapt_mass_migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_library` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_page_id` bigint(20) unsigned NOT NULL,
  `new_page_id` bigint(20) unsigned NOT NULL COMMENT 'same as question id',
  `migrated` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `adapt_mass_migrations_new_page_id_unique` (`new_page_id`),
  CONSTRAINT `adapt_mass_migrations_new_page_id_foreign` FOREIGN KEY (`new_page_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adapt_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `adapt_migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `original_library` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_page_id` bigint(20) unsigned NOT NULL,
  `new_page_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_emails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `analytics_dashboards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_dashboards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `analytics_course_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shared_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authorized` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `analytics_dashboards_course_id_unique` (`course_id`),
  CONSTRAINT `analytics_dashboards_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assign_to_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assign_to_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assign_to_timing_id` bigint(20) unsigned NOT NULL,
  `group` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assign_to_groups_assign_to_timing_id_foreign` (`assign_to_timing_id`),
  CONSTRAINT `assign_to_groups_assign_to_timing_id_foreign` FOREIGN KEY (`assign_to_timing_id`) REFERENCES `assign_to_timings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assign_to_timing_starts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assign_to_timing_starts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assign_to_timing_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `time_limit_override` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructor_adjusted` tinyint(1) NOT NULL DEFAULT '0',
  `extended_past_due` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assign_to_timing_starts_assign_to_timing_id_user_id_unique` (`assign_to_timing_id`,`user_id`),
  KEY `assign_to_timing_starts_user_id_foreign` (`user_id`),
  CONSTRAINT `assign_to_timing_starts_assign_to_timing_id_foreign` FOREIGN KEY (`assign_to_timing_id`) REFERENCES `assign_to_timings` (`id`),
  CONSTRAINT `assign_to_timing_starts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assign_to_timings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assign_to_timings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `available_from` datetime NOT NULL,
  `due` datetime NOT NULL,
  `final_submission_deadline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_limit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assign_to_timings_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `assign_to_timings_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assign_to_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assign_to_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assign_to_timing_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assign_to_users_user_id_assign_to_timing_id_unique` (`user_id`,`assign_to_timing_id`),
  KEY `assign_to_users_assign_to_timing_id_index` (`assign_to_timing_id`),
  CONSTRAINT `assign_to_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_grader_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_grader_access` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `access_level` tinyint(1) NOT NULL COMMENT '1 = Full access, 0 = No access',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_grader_assignment_id_user_id_unique` (`assignment_id`,`user_id`),
  KEY `assignment_grader_user_id_foreign` (`user_id`),
  CONSTRAINT `assignment_grader_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `assignment_grader_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_group_weights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_group_weights` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_group_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `assignment_group_weight` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_group_weights_assignment_group_id_course_id_unique` (`assignment_group_id`,`course_id`),
  KEY `assignment_group_weights_course_id_foreign` (`course_id`),
  CONSTRAINT `assignment_group_weights_assignment_group_id_foreign` FOREIGN KEY (`assignment_group_id`) REFERENCES `assignment_groups` (`id`),
  CONSTRAINT `assignment_group_weights_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_group` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `course_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_level_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_level_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_level_overrides_user_id_assignment_id_unique` (`user_id`,`assignment_id`),
  KEY `assignment_level_overrides_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `assignment_level_overrides_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `assignment_level_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_question` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_revision_id` bigint(20) unsigned DEFAULT NULL,
  `open_ended_submission_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_submit_work_override` tinyint(3) unsigned DEFAULT NULL,
  `open_ended_text_editor` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `open_ended_default_text` varchar(10000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discuss_it_settings` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flashcard_card_settings` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `forge_settings` json DEFAULT NULL,
  `forge_iteration_index` smallint(5) unsigned DEFAULT NULL,
  `points` decimal(8,4) NOT NULL,
  `weight` decimal(8,4) DEFAULT NULL,
  `custom_rubric` json DEFAULT NULL,
  `use_existing_rubric` tinyint(3) unsigned DEFAULT NULL,
  `completion_scoring_mode` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignment_information_shown_in_iframe` tinyint(1) NOT NULL DEFAULT '0',
  `submission_information_shown_in_iframe` tinyint(1) NOT NULL DEFAULT '0',
  `attribution_information_shown_in_iframe` tinyint(1) NOT NULL DEFAULT '0',
  `clicker_start` datetime DEFAULT NULL,
  `clicker_end` datetime DEFAULT NULL,
  `custom_clicker_time_to_submit` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `release_solution_when_question_is_closed` smallint(5) unsigned NOT NULL DEFAULT '1',
  `answer_shown` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `order` smallint(5) unsigned NOT NULL,
  `custom_question_title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignment_topic_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `discuss_it_chain_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_question_question_id_assignment_id_unique` (`question_id`,`assignment_id`),
  KEY `assignment_question_assignment_id_foreign` (`assignment_id`),
  KEY `assignment_question_assignment_topic_id_foreign` (`assignment_topic_id`),
  KEY `assignment_question_question_revision_id_index` (`question_revision_id`),
  KEY `assignment_question_discuss_it_chain_id_index` (`discuss_it_chain_id`),
  CONSTRAINT `assignment_question_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `assignment_question_assignment_topic_id_foreign` FOREIGN KEY (`assignment_topic_id`) REFERENCES `assignment_topics` (`id`),
  CONSTRAINT `assignment_question_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_question_case_study_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_question_case_study_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `case_study_notes_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_question_case_study_notes_unique` (`assignment_id`,`question_id`,`case_study_notes_id`),
  KEY `assignment_question_case_study_notes_question_id_foreign` (`question_id`),
  KEY `assignment_question_case_study_notes_case_study_notes_id_foreign` (`case_study_notes_id`),
  CONSTRAINT `assignment_question_case_study_notes_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `assignment_question_case_study_notes_case_study_notes_id_foreign` FOREIGN KEY (`case_study_notes_id`) REFERENCES `case_study_notes` (`id`),
  CONSTRAINT `assignment_question_case_study_notes_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_question_forge_draft`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_question_forge_draft` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_question_id` bigint(20) unsigned NOT NULL,
  `forge_draft_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assignment_question_forge_draft_assignment_question_id_foreign` (`assignment_question_id`),
  KEY `assignment_question_forge_draft_forge_draft_id_index` (`forge_draft_id`),
  CONSTRAINT `assignment_question_forge_draft_assignment_question_id_foreign` FOREIGN KEY (`assignment_question_id`) REFERENCES `assignment_question` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_question_learning_tree`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_question_learning_tree` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_question_id` bigint(20) unsigned NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `learning_tree` json DEFAULT NULL,
  `number_of_successful_paths_for_a_reset` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assignment_question_learning_tree_assignment_question_id_foreign` (`assignment_question_id`),
  KEY `assignment_question_learning_tree_learning_tree_id_foreign` (`learning_tree_id`),
  CONSTRAINT `assignment_question_learning_tree_assignment_question_id_foreign` FOREIGN KEY (`assignment_question_id`) REFERENCES `assignment_question` (`id`),
  CONSTRAINT `assignment_question_learning_tree_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_question_time_on_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_question_time_on_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `time_on_task` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_assignment_question_unique` (`user_id`,`assignment_id`,`question_id`),
  KEY `assignment_question_time_spents_assignment_id_foreign` (`assignment_id`),
  KEY `assignment_question_time_spents_question_id_foreign` (`question_id`),
  CONSTRAINT `assignment_question_time_spents_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `assignment_question_time_spents_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `assignment_question_time_spents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_show_points_per_question_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_show_points_per_question_backups` (
  `assignment_id` bigint(20) unsigned NOT NULL,
  `show_points_per_question` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_template_show_points_per_question_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_template_show_points_per_question_backups` (
  `assignment_template_id` bigint(20) unsigned NOT NULL,
  `show_points_per_question` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`assignment_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `template_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_description` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `private_description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formative` tinyint(4) NOT NULL,
  `assessment_type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flashcard_settings` json DEFAULT NULL,
  `can_submit_work` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `submitted_work_format` json DEFAULT NULL,
  `submitted_work_policy` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_contact_instructor_auto_graded` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'never',
  `number_of_allowed_attempts` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'applies to real time assignments',
  `number_of_allowed_attempts_penalty` smallint(6) DEFAULT NULL COMMENT 'applies to real time assignments',
  `can_view_hint` tinyint(1) DEFAULT NULL COMMENT 'applies to non-delayed assessments',
  `hint_penalty` smallint(5) unsigned DEFAULT NULL COMMENT 'applies to non-delayed assessments',
  `algorithmic` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `number_of_successful_paths_for_a_reset` smallint(5) unsigned DEFAULT NULL,
  `min_number_of_minutes_in_exposition_node` smallint(5) unsigned DEFAULT NULL,
  `reset_node_after_incorrect_attempt` tinyint(3) unsigned DEFAULT NULL,
  `assignment_group_id` bigint(20) unsigned NOT NULL,
  `source` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a' COMMENT 'a=adapt, x=external',
  `instructions` varchar(10000) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `number_of_randomized_assessments` tinyint(3) unsigned DEFAULT NULL,
  `external_source_points` smallint(5) unsigned DEFAULT NULL,
  `scoring_type` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'p=points, c=completed',
  `points_per_question` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'question weight/number of points',
  `default_completion_scoring_mode` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_points_per_question` decimal(8,2) DEFAULT NULL,
  `total_points` decimal(8,2) DEFAULT NULL,
  `default_clicker_time_to_submit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_upload_mode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_open_ended_submission_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_open_ended_text_editor` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `late_policy` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'not accepted, marked late, deduction',
  `late_deduction_percent` smallint(5) unsigned DEFAULT NULL,
  `late_deduction_application_period` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'once or x time intervals',
  `shown` tinyint(1) NOT NULL DEFAULT '1',
  `show_scores` tinyint(1) NOT NULL DEFAULT '0',
  `solutions_released` tinyint(1) NOT NULL DEFAULT '0',
  `solutions_availability` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `graders_can_see_student_names` tinyint(1) NOT NULL DEFAULT '1',
  `students_can_view_assignment_statistics` tinyint(1) NOT NULL DEFAULT '0',
  `include_in_weighted_average` tinyint(1) NOT NULL,
  `notifications` tinyint(1) NOT NULL,
  `lms_resource_link_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `textbook_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assign_to_everyone` tinyint(3) unsigned NOT NULL,
  `order` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assignments_assignment_group_id_foreign` (`assignment_group_id`),
  KEY `assignment_templates_user_id_foreign` (`user_id`),
  CONSTRAINT `assignment_templates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assignment_topics_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `assignment_topics_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_description` text COLLATE utf8mb4_unicode_ci,
  `private_description` text COLLATE utf8mb4_unicode_ci,
  `formative` tinyint(4) NOT NULL,
  `assessment_type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flashcard_settings` json DEFAULT NULL,
  `can_submit_work` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `submitted_work_format` json DEFAULT NULL,
  `submitted_work_policy` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_contact_instructor_auto_graded` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'never',
  `number_of_allowed_attempts` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'applies to real time assignments',
  `number_of_allowed_attempts_penalty` smallint(6) DEFAULT NULL COMMENT 'applies to real time assignments',
  `can_view_hint` tinyint(1) DEFAULT NULL COMMENT 'applies to non-delayed assessments',
  `hint_penalty` smallint(5) unsigned DEFAULT NULL COMMENT 'applies to non-delayed assessments',
  `algorithmic` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `number_of_successful_paths_for_a_reset` smallint(5) unsigned DEFAULT NULL,
  `min_number_of_minutes_in_exposition_node` decimal(4,2) DEFAULT NULL COMMENT 'in minutes',
  `reset_node_after_incorrect_attempt` tinyint(3) unsigned DEFAULT NULL,
  `assignment_group_id` bigint(20) unsigned NOT NULL,
  `source` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a' COMMENT 'a=adapt, x=external',
  `instructions` varchar(10000) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `common_question_text` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_of_randomized_assessments` tinyint(3) unsigned DEFAULT NULL,
  `external_source_points` smallint(5) unsigned DEFAULT NULL,
  `scoring_type` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'p=points, c=completed',
  `points_per_question` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'question weight/number of points',
  `default_completion_scoring_mode` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_points_per_question` decimal(8,2) DEFAULT NULL,
  `total_points` decimal(8,2) DEFAULT NULL,
  `default_clicker_time_to_submit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_upload_mode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_open_ended_submission_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_open_ended_text_editor` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `late_policy` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'not accepted, marked late, deduction',
  `late_deduction_percent` smallint(5) unsigned DEFAULT NULL,
  `late_deduction_application_period` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'once or x time intervals',
  `shown` tinyint(1) NOT NULL DEFAULT '1',
  `show_scores` tinyint(1) NOT NULL DEFAULT '0',
  `solutions_released` tinyint(1) NOT NULL DEFAULT '0',
  `solutions_availability` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_titles_shown` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `question_url_view` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assignment',
  `graders_can_see_student_names` tinyint(1) NOT NULL DEFAULT '1',
  `students_can_view_assignment_statistics` tinyint(1) NOT NULL DEFAULT '0',
  `include_in_weighted_average` tinyint(1) NOT NULL,
  `notifications` tinyint(1) NOT NULL,
  `lms_grade_passback` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `lms_resource_link_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lms_assignment_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lms_assignment_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lms_assignment_group_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `textbook_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assignments_course_id_foreign` (`course_id`),
  KEY `assignments_assignment_group_id_foreign` (`assignment_group_id`),
  KEY `assignments_lms_resource_link_id_index` (`lms_resource_link_id`),
  CONSTRAINT `assignments_assignment_group_id_foreign` FOREIGN KEY (`assignment_group_id`) REFERENCES `assignment_groups` (`id`),
  CONSTRAINT `assignments_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignments_recomputed_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments_recomputed_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `old_score` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_score` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auto_releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auto_releases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_id` bigint(20) unsigned NOT NULL,
  `shown` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shown_activated` tinyint(3) unsigned DEFAULT NULL,
  `show_scores` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_scores_after` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_scores_activated` tinyint(3) unsigned DEFAULT NULL,
  `solutions_released` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solutions_released_after` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solutions_released_activated` tinyint(3) unsigned DEFAULT NULL,
  `students_can_view_assignment_statistics` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `students_can_view_assignment_statistics_after` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `students_can_view_assignment_statistics_activated` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auto_releases_type_type_id_unique` (`type`,`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bad_webworks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bad_webworks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bad_webworks_question_id_unique` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `beta_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beta_assignments` (
  `id` bigint(20) unsigned NOT NULL,
  `alpha_assignment_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `beta_assignments_id_alpha_assignment_id_unique` (`id`,`alpha_assignment_id`),
  KEY `beta_assignments_alpha_assignment_id_foreign` (`alpha_assignment_id`),
  CONSTRAINT `beta_assignments_alpha_assignment_id_foreign` FOREIGN KEY (`alpha_assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `beta_assignments_id_foreign` FOREIGN KEY (`id`) REFERENCES `assignments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `beta_course_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beta_course_approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `beta_assignment_id` bigint(20) unsigned NOT NULL,
  `beta_question_id` bigint(20) unsigned NOT NULL,
  `beta_learning_tree_id` bigint(20) unsigned NOT NULL,
  `action` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `beta_course_approvals_beta_assignment_id_foreign` (`beta_assignment_id`),
  KEY `beta_course_approvals_beta_question_id_foreign` (`beta_question_id`),
  CONSTRAINT `beta_course_approvals_beta_assignment_id_foreign` FOREIGN KEY (`beta_assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `beta_course_approvals_beta_question_id_foreign` FOREIGN KEY (`beta_question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `beta_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beta_courses` (
  `id` bigint(20) unsigned NOT NULL,
  `alpha_course_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `beta_courses_id_alpha_course_id_unique` (`id`,`alpha_course_id`),
  KEY `beta_courses_alpha_course_id_foreign` (`alpha_course_id`),
  CONSTRAINT `beta_courses_alpha_course_id_foreign` FOREIGN KEY (`alpha_course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `beta_courses_id_foreign` FOREIGN KEY (`id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branches_learning_tree_id_user_id_question_id_unique` (`learning_tree_id`,`user_id`,`question_id`),
  KEY `branches_user_id_foreign` (`user_id`),
  KEY `branches_question_id_foreign` (`question_id`),
  CONSTRAINT `branches_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `branches_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `branches_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `can_give_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `can_give_ups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `can_give_ups_user_id_assignment_id_question_id_unique` (`user_id`,`assignment_id`,`question_id`),
  KEY `can_give_ups_assignment_id_foreign` (`assignment_id`),
  KEY `can_give_ups_question_id_foreign` (`question_id`),
  CONSTRAINT `can_give_ups_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `can_give_ups_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `can_give_ups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canned_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canned_responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `canned_response` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `canned_responses_canned_response_id_unique` (`canned_response`,`id`),
  KEY `canned_responses_user_id_foreign` (`user_id`),
  CONSTRAINT `canned_responses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canvas_max_attempts_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canvas_max_attempts_errors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `sent_email` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `canvas_max_attempts_errors_assignment_id_unique` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canvas_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canvas_updates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `updated_points` tinyint(4) NOT NULL DEFAULT '0',
  `updated_everybodys` tinyint(4) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canvas_vanity_urls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canvas_vanity_urls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lti_registration_id` bigint(20) unsigned NOT NULL,
  `vanity_url` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `canvas_vanity_urls_lti_registration_id_foreign` (`lti_registration_id`),
  CONSTRAINT `canvas_vanity_urls_lti_registration_id_foreign` FOREIGN KEY (`lti_registration_id`) REFERENCES `lti_registrations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `case_study_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `case_study_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci,
  `first_application` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `case_study_notes_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `case_study_notes_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloudflare_stream_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloudflare_stream_credentials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signing_key_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloudflare_stream_videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloudflare_stream_videos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `s3_path` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cloudflare_uid` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `times_served` int(10) unsigned NOT NULL DEFAULT '0',
  `captions_hash` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','processing','ready','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `marked_for_deletion` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cloudflare_stream_videos_s3_path_unique` (`s3_path`),
  KEY `cloudflare_stream_videos_cloudflare_uid_index` (`cloudflare_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `co_instructors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `co_instructors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `access_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `co_instructors_course_id_user_id_unique` (`course_id`,`user_id`),
  KEY `co_instructors_user_id_foreign` (`user_id`),
  CONSTRAINT `co_instructors_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `co_instructors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `co_question_editors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `co_question_editors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_editor_user_id` bigint(20) unsigned NOT NULL,
  `co_question_editor_user_id` bigint(20) unsigned NOT NULL,
  `access_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commons_course_name_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commons_course_name_mappings` (
  `name` text COLLATE utf8mb4_unicode_ci,
  `mapping` text COLLATE utf8mb4_unicode_ci,
  `course_id` bigint(20) DEFAULT NULL,
  `id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `compiled_pdf_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compiled_pdf_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `set_page_only` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `compiled_pdf_overrides_user_id_assignment_id_unique` (`user_id`,`assignment_id`),
  KEY `compiled_pdf_overrides_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `compiled_pdf_overrides_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `compiled_pdf_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_grader_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_grader_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_grader_overrides_course_id_foreign` (`course_id`),
  KEY `contact_grader_overrides_user_id_foreign` (`user_id`),
  CONSTRAINT `contact_grader_overrides_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `access_code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NOT NULL,
  `last_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(3000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_invitations_course_id_foreign` (`course_id`),
  KEY `course_invitations_section_id_foreign` (`section_id`),
  CONSTRAINT `course_invitations_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `course_invitations_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `order` mediumint(8) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_orders_course_id_user_id_unique` (`course_id`,`user_id`),
  KEY `course_orders_user_id_foreign` (`user_id`),
  CONSTRAINT `course_orders_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `course_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discipline_id` bigint(20) unsigned DEFAULT NULL,
  `public_description` text COLLATE utf8mb4_unicode_ci,
  `private_description` text COLLATE utf8mb4_unicode_ci,
  `textbook_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `term` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `order` mediumint(8) unsigned NOT NULL,
  `public` tinyint(1) NOT NULL,
  `lms` tinyint(1) NOT NULL DEFAULT '0',
  `lms_only_entry` tinyint(4) NOT NULL DEFAULT '1',
  `adapt_enrollment_notification_date` datetime DEFAULT NULL,
  `lms_course_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lms_course_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_numbers_shown_in_iframe` tinyint(1) NOT NULL DEFAULT '0',
  `alpha` tinyint(1) NOT NULL,
  `anonymous_users` tinyint(1) NOT NULL,
  `formative` tinyint(1) NOT NULL DEFAULT '0',
  `beta_approval_notifications` tinyint(1) NOT NULL DEFAULT '1',
  `school_id` bigint(20) unsigned NOT NULL DEFAULT '1',
  `shown` tinyint(1) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `show_z_scores` tinyint(1) NOT NULL DEFAULT '0',
  `show_progress_report` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `auto_update_question_revisions` tinyint(1) NOT NULL DEFAULT '0',
  `students_can_view_weighted_average` tinyint(1) NOT NULL DEFAULT '0',
  `auto_release_shown` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_release_show_scores` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_release_show_scores_after` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_release_solutions_released` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_release_solutions_released_after` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_release_students_can_view_assignment_statistics` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_release_students_can_view_assignment_statistics_after` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `courses_user_id_foreign` (`user_id`),
  KEY `courses_school_id_foreign` (`school_id`),
  KEY `courses_end_date_index` (`end_date`),
  CONSTRAINT `courses_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `courses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cron_job_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cron_job_times` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `command` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time (seconds)` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `current_question_editors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `current_question_editors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cutups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cutups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cutups_assignment_id_foreign` (`assignment_id`),
  KEY `cutups_user_id_foreign` (`user_id`),
  CONSTRAINT `cutups_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `cutups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_shops` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `anon_student_id` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `submission_time` timestamp NULL DEFAULT NULL,
  `review_time_start` timestamp NULL DEFAULT NULL,
  `review_time_end` timestamp NULL DEFAULT NULL,
  `assignment_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assignment_name` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignment_group` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignment_scoring_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'p=performance,c=completion',
  `assignment_points` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_of_attempts_allowed` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_points` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `library` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submission_count` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CORRECT, INCORRECT, HINT',
  `due` datetime DEFAULT NULL,
  `school` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `course_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `textbook_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructor_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructor_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course_start_date` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `data_shops_course_id_index` (`course_id`),
  KEY `data_shops_school_index` (`school`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_shops_complete`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_shops_complete` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `anon_student_id` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `submission_time` timestamp NULL DEFAULT NULL,
  `review_time_start` timestamp NULL DEFAULT NULL,
  `review_time_end` timestamp NULL DEFAULT NULL,
  `assignment_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assignment_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignment_group` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignment_scoring_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'p=performance,c=completion',
  `assignment_points` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_of_attempts_allowed` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_points` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `library` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submission_count` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CORRECT, INCORRECT, HINT',
  `due` datetime DEFAULT NULL,
  `school` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `course_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `textbook_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructor_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructor_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course_start_date` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `data_shops_course_id_index` (`course_id`),
  KEY `data_shops_school_index` (`school`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_shops_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_shops_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `course_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `term` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructor_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_of_enrolled_students` smallint(5) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `question_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `disciplines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disciplines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discuss_it_chains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discuss_it_chains` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discuss_it_chains_course_id_question_id_unique` (`course_id`,`question_id`),
  KEY `discuss_it_chains_question_id_index` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discussion_comment_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discussion_comment_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `discussion_comment_id` bigint(20) unsigned NOT NULL,
  `file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` smallint(5) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dca_discussion_comment_id_index` (`discussion_comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discussion_comment_timestamps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discussion_comment_timestamps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `discussion_comment_id` bigint(20) unsigned NOT NULL,
  `referenced_discussion_comment_id` bigint(20) unsigned NOT NULL,
  `seconds` decimal(10,2) NOT NULL,
  `order` smallint(5) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dct_discussion_comment_id_index` (`discussion_comment_id`),
  KEY `dct_referenced_discussion_comment_id_index` (`referenced_discussion_comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discussion_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discussion_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `discussion_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `text` varchar(10000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pasted_comment` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `file` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recording_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transcript` text COLLATE utf8mb4_unicode_ci,
  `re_processed_transcript` tinyint(4) NOT NULL DEFAULT '0',
  `status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `satisfied_requirement` tinyint(1) NOT NULL DEFAULT '0',
  `mp4_status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mp4_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `posted_in_assignment_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discussion_comments_user_id_foreign` (`user_id`),
  KEY `discussion_comments_discussion_id_foreign` (`discussion_id`),
  KEY `discussion_comments_posted_in_assignment_id_index` (`posted_in_assignment_id`),
  CONSTRAINT `discussion_comments_discussion_id_foreign` FOREIGN KEY (`discussion_id`) REFERENCES `discussions` (`id`),
  CONSTRAINT `discussion_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discussion_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discussion_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `group` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discussion_groups_question_user_assignment_index` (`question_id`,`user_id`,`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discussions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discussions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `media_upload_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `group` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discussions_assignment_id_foreign` (`assignment_id`),
  KEY `discussions_question_id_foreign` (`question_id`),
  KEY `discussions_user_id_foreign` (`user_id`),
  KEY `discussions_media_upload_id_foreign` (`media_upload_id`),
  CONSTRAINT `discussions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `discussions_media_upload_id_foreign` FOREIGN KEY (`media_upload_id`) REFERENCES `question_media_uploads` (`id`),
  CONSTRAINT `discussions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `discussions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_central_identity_id`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_central_identity_id` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `central_identity_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_central_identity_id_central_identity_id_email_unique` (`central_identity_id`,`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empty_learning_tree_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empty_learning_tree_nodes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empty_learning_tree_nodes_question_id_foreign` (`question_id`),
  CONSTRAINT `empty_learning_tree_nodes_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empty_paragraph_non_technology_html_fixes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empty_paragraph_non_technology_html_fixes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `non_technology_html` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empty_paragraph_question_media_upload_fixes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empty_paragraph_question_media_upload_fixes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_media_upload_id` bigint(20) unsigned NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `a11y_redirect` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollments_user_id_course_id_unique` (`user_id`,`course_id`),
  UNIQUE KEY `enrollments_user_id_section_id_unique` (`user_id`,`section_id`),
  KEY `enrollments_course_id_foreign` (`course_id`),
  KEY `enrollments_section_id_foreign` (`section_id`),
  CONSTRAINT `enrollments_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `enrollments_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `enrollments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `execution_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `execution_times` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `method` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parameters` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `execution_time` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expired_problem_jwt_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expired_problem_jwt_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `assignment_id` bigint(20) unsigned DEFAULT NULL,
  `question_id` bigint(20) unsigned DEFAULT NULL,
  `technology` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jti` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iat` bigint(20) DEFAULT NULL,
  `exp` bigint(20) DEFAULT NULL,
  `nbf` bigint(20) DEFAULT NULL,
  `processed_at` bigint(20) NOT NULL,
  `seconds_past_exp` int(11) DEFAULT NULL,
  `raw_claims` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expired_problem_jwt_logs_user_id_index` (`user_id`),
  KEY `expired_problem_jwt_logs_assignment_id_index` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `extensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `extensions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `extension` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extensions_user_id_assignment_id_unique` (`user_id`,`assignment_id`),
  KEY `extensions_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `extensions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `extensions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `extra_credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `extra_credits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `extra_credit` decimal(5,2) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extra_credits_user_id_course_id_unique` (`user_id`,`course_id`),
  KEY `extra_credits_course_id_foreign` (`course_id`),
  CONSTRAINT `extra_credits_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `extra_credits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fcm_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fcm_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `response` varchar(2500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fcm_log_user_id_foreign` (`user_id`),
  CONSTRAINT `fcm_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fcm_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fcm_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `fcm_token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fcm_tokens_user_id_fcm_token_unique` (`user_id`,`fcm_token`),
  CONSTRAINT `fcm_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `file_upload_mode_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_upload_mode_backups` (
  `assignment_id` bigint(20) unsigned NOT NULL,
  `file_upload_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `final_grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `final_grades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `letter_grades` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `round_scores` tinyint(1) NOT NULL DEFAULT '0',
  `letter_grades_released` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `letter_grades_course_id_unique` (`course_id`),
  CONSTRAINT `letter_grades_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fix_forge_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fix_forge_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `score` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'these scores were 0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fix_forge_scores_user_id_assignment_id_unique` (`user_id`,`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fixed_stylings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fixed_stylings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch` smallint(5) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `non_technology_html` text COLLATE utf8mb4_unicode_ci,
  `qti_json` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fixed_stylings_question_id_unique` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flashcard_ai_audio_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flashcard_ai_audio_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_revision_id` bigint(20) unsigned NOT NULL,
  `side` enum('front','back') COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_type` enum('tts','vtt') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('processing','success','error') COLLATE utf8mb4_unicode_ci NOT NULL,
  `s3_key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `model` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voice` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fc_ai_audio_logs_unique` (`question_id`,`question_revision_id`,`side`,`job_type`),
  KEY `flashcard_ai_audio_logs_question_id_index` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flashcard_audio_vtt_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flashcard_audio_vtt_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_revision_id` bigint(20) unsigned NOT NULL,
  `side` enum('front','back') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('processing','success','error') COLLATE utf8mb4_unicode_ci NOT NULL,
  `s3_key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `model` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whisper-1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fc_audio_vtt_logs_unique` (`question_id`,`question_revision_id`,`side`),
  KEY `flashcard_audio_vtt_logs_question_id_index` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forge_assignment_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forge_assignment_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `adapt_question_id` bigint(20) unsigned NOT NULL,
  `adapt_assignment_id` bigint(20) unsigned NOT NULL,
  `forge_question_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `forge_class_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `adapt_forge_question_unique` (`adapt_assignment_id`,`adapt_question_id`,`forge_question_id`,`forge_class_id`),
  KEY `forge_assignment_questions_adapt_question_id_foreign` (`adapt_question_id`),
  CONSTRAINT `forge_assignment_questions_adapt_assignment_id_foreign` FOREIGN KEY (`adapt_assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `forge_assignment_questions_adapt_question_id_foreign` FOREIGN KEY (`adapt_question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forge_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forge_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forge_enrollments_user_id_course_id_unique` (`user_id`,`course_id`),
  KEY `forge_enrollments_course_id_foreign` (`course_id`),
  CONSTRAINT `forge_enrollments_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forge_user_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forge_user_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `formatted_question_type_technology`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `formatted_question_type_technology` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `formatted_question_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `technology` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `formatted_question_type_technology_unique` (`formatted_question_type`,`technology`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `formatted_question_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `formatted_question_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `formatted_question_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_id_type_unique` (`question_id`,`formatted_question_type`),
  CONSTRAINT `formatted_question_types_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `framework_descriptors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `framework_descriptors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `descriptor` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `framework_item_learning_tree`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `framework_item_learning_tree` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `framework_item_id` bigint(20) unsigned NOT NULL,
  `framework_item_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `framework_item_learning_tree_learning_tree_id_foreign` (`learning_tree_id`),
  KEY `fw_item_learning_tree_item_id_type_index` (`framework_item_id`,`framework_item_type`),
  CONSTRAINT `framework_item_learning_tree_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `framework_item_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `framework_item_question` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `framework_item_id` bigint(20) unsigned NOT NULL,
  `framework_item_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `framework_item_question_question_id_foreign` (`question_id`),
  CONSTRAINT `framework_item_question_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `framework_level_framework_descriptor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `framework_level_framework_descriptor` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `framework_level_id` bigint(20) unsigned NOT NULL,
  `framework_descriptor_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_framework_level_framework_descriptor` (`framework_level_id`,`framework_descriptor_id`),
  KEY `framework_descriptor_foreign` (`framework_descriptor_id`),
  CONSTRAINT `framework_descriptor_foreign` FOREIGN KEY (`framework_descriptor_id`) REFERENCES `framework_descriptors` (`id`),
  CONSTRAINT `framework_level_foreign` FOREIGN KEY (`framework_level_id`) REFERENCES `framework_levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `framework_level_learning_objective`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `framework_level_learning_objective` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `framework_level_id` bigint(20) unsigned NOT NULL,
  `learning_objective_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `framework_level_learning_objective_framework_level_id_foreign` (`framework_level_id`),
  KEY `framework_level_learning_objective_learning_objective_id_foreign` (`learning_objective_id`),
  CONSTRAINT `framework_level_learning_objective_framework_level_id_foreign` FOREIGN KEY (`framework_level_id`) REFERENCES `framework_levels` (`id`),
  CONSTRAINT `framework_level_learning_objective_learning_objective_id_foreign` FOREIGN KEY (`learning_objective_id`) REFERENCES `learning_objectives` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `framework_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `framework_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `framework_id` bigint(20) unsigned NOT NULL,
  `level` tinyint(3) unsigned NOT NULL,
  `title` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` tinyint(3) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `framework_levels_framework_id_foreign` (`framework_id`),
  CONSTRAINT `framework_levels_framework_id_foreign` FOREIGN KEY (`framework_id`) REFERENCES `frameworks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frameworks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frameworks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descriptor_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_version` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_url` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `frameworks_title_user_id_unique` (`title`,`user_id`),
  KEY `frameworks_user_id_foreign` (`user_id`),
  CONSTRAINT `frameworks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grader_access_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grader_access_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `section_id` bigint(20) unsigned NOT NULL,
  `access_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grader_access_codes_section_id_foreign` (`section_id`),
  CONSTRAINT `grader_access_codes_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grader_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grader_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `when_assignments_are_closed` tinyint(1) DEFAULT NULL,
  `for_late_submissions` tinyint(1) DEFAULT NULL,
  `num_reminders_per_week` smallint(5) unsigned NOT NULL,
  `copy_grading_reminder_to_instructor` tinyint(1) DEFAULT NULL,
  `copy_grading_reminder_to_head_grader` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grader_notifications_course_id_foreign` (`course_id`),
  CONSTRAINT `grader_notifications_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `graders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `graders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `section_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `graders_user_id_section_id_unique` (`user_id`,`section_id`),
  KEY `graders_section_id_foreign` (`section_id`),
  CONSTRAINT `graders_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `graders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grading_styles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grading_styles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h5p_activity_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h5p_activity_sets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `sub_content_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `correct` tinyint(3) unsigned NOT NULL,
  `submission_count` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sub_content_id` (`user_id`,`assignment_id`,`question_id`,`sub_content_id`),
  KEY `h5p_video_interactions_assignment_id_foreign` (`assignment_id`),
  KEY `h5p_video_interactions_question_id_foreign` (`question_id`),
  CONSTRAINT `h5p_video_interactions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `h5p_video_interactions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `h5p_video_interactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h5p_adapt_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h5p_adapt_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adapt_status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `xapi` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `h5p_adapt_statuses_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h5p_id_adapt_question_id`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h5p_id_adapt_question_id` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `h5p_id` bigint(20) unsigned NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adapt_question_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h5p_max_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h5p_max_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `max_score` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `h5p_max_scores_question_id_foreign` (`question_id`),
  CONSTRAINT `h5p_max_scores_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `head_graders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `head_graders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `head_graders_course_id_user_id_unique` (`course_id`,`user_id`),
  KEY `head_graders_user_id_foreign` (`user_id`),
  CONSTRAINT `head_graders_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `head_graders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `huang_course_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `huang_course_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `technology_iframe` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `huang_course_questions_question_id_unique` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `instructor_access_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instructor_access_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `access_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `key_secrets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `key_secrets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `launch_redirects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `launch_redirects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `redirect_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_objective_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_objective_question` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `learning_objective_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `learning_objective_question_learning_objective_id_foreign` (`learning_objective_id`),
  KEY `learning_objective_question_question_id_foreign` (`question_id`),
  CONSTRAINT `learning_objective_question_learning_objective_id_foreign` FOREIGN KEY (`learning_objective_id`) REFERENCES `learning_objectives` (`id`),
  CONSTRAINT `learning_objective_question_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_objectives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_objectives` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_outcomes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_outcomes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `topic` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_analytics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructor` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assignment_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `root_node` tinyint(1) NOT NULL,
  `action` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `response` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `root_node_question_id` bigint(20) unsigned NOT NULL,
  `learning_tree` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `learning_tree_histories_learning_tree_id_foreign` (`learning_tree_id`),
  CONSTRAINT `learning_tree_histories_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_library` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_page_id` bigint(20) unsigned NOT NULL,
  `new_page_id` bigint(20) unsigned NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_learning_tree` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `migrated_learning_tree` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_node_descriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_node_descriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(10000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tree_question_user_unique` (`learning_tree_id`,`question_id`,`user_id`),
  KEY `learning_tree_node_descriptions_question_id_foreign` (`question_id`),
  KEY `learning_tree_node_descriptions_user_id_foreign` (`user_id`),
  CONSTRAINT `learning_tree_node_descriptions_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `learning_tree_node_descriptions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `learning_tree_node_descriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_node_learning_outcome`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_node_learning_outcome` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `learning_outcome_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `learning_tree_node_skill_learning_tree_id_foreign` (`learning_tree_id`),
  KEY `learning_tree_node_skill_question_id_foreign` (`question_id`),
  KEY `learning_tree_node_skill_skill_id_foreign` (`learning_outcome_id`),
  CONSTRAINT `learning_tree_node_skill_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `learning_tree_node_skill_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `learning_tree_node_skill_skill_id_foreign` FOREIGN KEY (`learning_outcome_id`) REFERENCES `learning_outcomes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_node_seeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_node_seeds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `seed` varchar(10000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `learning_tree_node_seeds_assignment_id_foreign` (`assignment_id`),
  KEY `learning_tree_node_seeds_learning_tree_id_foreign` (`learning_tree_id`),
  KEY `learning_tree_node_seeds_question_id_foreign` (`question_id`),
  KEY `learning_tree_node_seeds_user_id_foreign` (`user_id`),
  CONSTRAINT `learning_tree_node_seeds_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `learning_tree_node_seeds_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `learning_tree_node_seeds_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `learning_tree_node_seeds_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_node_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_node_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `time_spent` smallint(5) unsigned DEFAULT NULL COMMENT 'in seconds',
  `submission` text COLLATE utf8mb4_unicode_ci,
  `proportion_correct` decimal(8,4) DEFAULT NULL,
  `submission_count` smallint(5) unsigned DEFAULT '0',
  `completed` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `check_for_reset` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `show_submission_message` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `learning_tree_node_submissions_user_id_foreign` (`user_id`),
  KEY `learning_tree_node_submissions_learning_tree_id_foreign` (`learning_tree_id`),
  KEY `learning_tree_node_submissions_assignment_id_foreign` (`assignment_id`),
  KEY `learning_tree_node_submissions_question_id_foreign` (`question_id`),
  CONSTRAINT `learning_tree_node_submissions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `learning_tree_node_submissions_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `learning_tree_node_submissions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `learning_tree_node_submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `number_resets_available` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `learning_tree_resets_user_id_foreign` (`user_id`),
  KEY `learning_tree_resets_assignment_id_foreign` (`assignment_id`),
  KEY `learning_tree_resets_learning_tree_id_foreign` (`learning_tree_id`),
  CONSTRAINT `learning_tree_resets_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `learning_tree_resets_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `learning_tree_resets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_successful_branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_successful_branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `branch_id` tinyint(3) unsigned NOT NULL,
  `applied_to_reset` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `successful_branch_unique` (`user_id`,`assignment_id`,`learning_tree_id`,`branch_id`),
  KEY `learning_tree_successful_branches_assignment_id_foreign` (`assignment_id`),
  KEY `learning_tree_successful_branches_learning_tree_id_foreign` (`learning_tree_id`),
  CONSTRAINT `learning_tree_successful_branches_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `learning_tree_successful_branches_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `learning_tree_successful_branches_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_tag` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `learning_tree_tag_learning_tree_id_tag_id_unique` (`learning_tree_id`,`tag_id`),
  KEY `learning_tree_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `learning_tree_tag_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `learning_tree_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_tree_time_lefts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_tree_time_lefts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `level` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `time_left` smallint(5) unsigned NOT NULL COMMENT 'in seconds',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `learning_tree_time_lefts_unique` (`user_id`,`assignment_id`,`level`,`learning_tree_id`,`branch_id`),
  KEY `learning_tree_time_lefts_assignment_id_foreign` (`assignment_id`),
  KEY `learning_tree_time_lefts_learning_tree_id_foreign` (`learning_tree_id`),
  CONSTRAINT `learning_tree_time_lefts_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `learning_tree_time_lefts_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `learning_tree_time_lefts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_trees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_trees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` varchar(5000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public` tinyint(4) NOT NULL,
  `root_node_question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `learning_tree` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_history_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `question_subject_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_chapter_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_section_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `learning_trees_user_id_foreign` (`user_id`),
  KEY `learning_trees_current_history_id_foreign` (`current_history_id`),
  KEY `learning_trees_question_subject_id_index` (`question_subject_id`),
  KEY `learning_trees_question_chapter_id_index` (`question_chapter_id`),
  KEY `learning_trees_question_section_id_index` (`question_section_id`),
  CONSTRAINT `learning_trees_current_history_id_foreign` FOREIGN KEY (`current_history_id`) REFERENCES `learning_tree_histories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `learning_trees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `libre_one_access_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `libre_one_access_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `access_code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `libre_one_access_codes_course_id_foreign` (`course_id`),
  CONSTRAINT `libre_one_access_codes_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `libreone_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `libreone_credentials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bearer_token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_secret` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `link_to_account_validation_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `link_to_account_validation_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validation_code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `linked_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `linked_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `linked_to_user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `linked_accounts_user_id_linked_to_user_id_unique` (`user_id`,`linked_to_user_id`),
  KEY `linked_accounts_linked_to_user_id_foreign` (`linked_to_user_id`),
  CONSTRAINT `linked_accounts_linked_to_user_id_foreign` FOREIGN KEY (`linked_to_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `linked_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lms_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lms_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `school_id` bigint(20) unsigned NOT NULL,
  `lms` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refresh_token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_access_tokens_school_id_foreign` (`school_id`),
  CONSTRAINT `lms_access_tokens_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lms_outage_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lms_outage_courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `school_id` bigint(20) unsigned NOT NULL,
  `lti_registration_id` bigint(20) unsigned DEFAULT NULL,
  `lms_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `turned_on_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_outage_courses_course_id_foreign` (`course_id`),
  KEY `lms_outage_courses_school_id_foreign` (`school_id`),
  KEY `lms_outage_courses_lti_registration_id_foreign` (`lti_registration_id`),
  KEY `lms_outage_courses_turned_on_at_index` (`turned_on_at`),
  KEY `lms_outage_courses_lms_type_index` (`lms_type`),
  CONSTRAINT `lms_outage_courses_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `lms_outage_courses_lti_registration_id_foreign` FOREIGN KEY (`lti_registration_id`) REFERENCES `lti_registrations` (`id`),
  CONSTRAINT `lms_outage_courses_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_commands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_commands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `command` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `logged_in_user_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logged_in_user_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logged_out` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `logged_in_user_tokens_email_index` (`email`),
  KEY `logged_in_user_tokens_sid_index` (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_as_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_as_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_user_id` bigint(20) unsigned NOT NULL,
  `logged_in_as_user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_as_users_original_user_id_foreign` (`original_user_id`),
  KEY `login_as_users_logged_in_as_user_id_foreign` (`logged_in_as_user_id`),
  CONSTRAINT `login_as_users_logged_in_as_user_id_foreign` FOREIGN KEY (`logged_in_as_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `login_as_users_original_user_id_foreign` FOREIGN KEY (`original_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_assignments_and_grades_urls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_assignments_and_grades_urls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lti_assignments_and_grades_urls_assignment_id_unique` (`assignment_id`),
  UNIQUE KEY `lti_assignments_and_grades_urls_url_unique` (`url`),
  CONSTRAINT `lti_assignments_and_grades_urls_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_deployments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_deployments` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registration_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lti_deployments_registration_id_foreign` (`registration_id`),
  CONSTRAINT `lti_deployments_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `lti_registrations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_grade_passbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_grade_passbacks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `launch_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lti_grade_passbacks_assignment_id_index` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `private_key_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alg` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_launches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_launches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned DEFAULT NULL,
  `launch_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jwt_body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lti_launches_user_id_assignment_id_unique` (`user_id`,`assignment_id`),
  KEY `lti_launches_assignment_id_foreign` (`assignment_id`),
  KEY `lti_launches_launch_id_index` (`launch_id`),
  CONSTRAINT `lti_launches_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `lti_launches_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_names_and_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_names_and_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lms_user_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emailed_about_account` datetime DEFAULT NULL,
  `auto_provisioned_message` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lti_names_and_roles_lms_user_id_course_id_unique` (`lms_user_id`,`course_id`),
  KEY `lti_names_and_roles_course_id_foreign` (`course_id`),
  CONSTRAINT `lti_names_and_roles_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_names_and_roles_urls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_names_and_roles_urls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lti_names_and_roles_urls_course_id_unique` (`course_id`),
  UNIQUE KEY `lti_names_and_roles_urls_url_unique` (`url`),
  CONSTRAINT `lti_names_and_roles_urls_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_pending_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_pending_registrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campus_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_registrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campus_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iss` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_secret` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_login_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth_token_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth_server` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_set_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lti_key_id` bigint(20) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lti_registrations_iss_client_id_unique` (`iss`,`client_id`),
  KEY `lti_registrations_lti_key_id_foreign` (`lti_key_id`),
  CONSTRAINT `lti_registrations_lti_key_id_foreign` FOREIGN KEY (`lti_key_id`) REFERENCES `lti_keys` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_schools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lti_registration_id` bigint(20) unsigned NOT NULL,
  `school_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lti_schools_lti_registration_id_school_id_unique` (`lti_registration_id`,`school_id`),
  KEY `lti_schools_school_id_foreign` (`school_id`),
  CONSTRAINT `lti_schools_lti_registration_id_foreign` FOREIGN KEY (`lti_registration_id`) REFERENCES `lti_registrations` (`id`),
  CONSTRAINT `lti_schools_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lti_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lti_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lti_token_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lti_token` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `maximum_number_of_allowed_attempts_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maximum_number_of_allowed_attempts_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `max_number_allowed_passbacks_foreign` (`assignment_id`),
  CONSTRAINT `max_number_allowed_passbacks_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mind_touch_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mind_touch_events` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` bigint(20) unsigned NOT NULL,
  `event_time` timestamp NOT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mind_touch_events_page_id_event_time_unique` (`page_id`,`event_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `my_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `my_favorites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `folder_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned DEFAULT NULL,
  `open_ended_submission_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `open_ended_text_editor` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `learning_tree_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saved_questions_user_id_question_id_unique` (`user_id`,`question_id`),
  KEY `saved_questions_question_id_foreign` (`question_id`),
  KEY `saved_questions_learning_tree_id_foreign` (`learning_tree_id`),
  KEY `saved_questions_folder_id_foreign` (`folder_id`),
  CONSTRAINT `saved_questions_folder_id_foreign` FOREIGN KEY (`folder_id`) REFERENCES `saved_questions_folders` (`id`),
  CONSTRAINT `saved_questions_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `saved_questions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `no_student_access_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `no_student_access_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `no_user_logged_in_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `no_user_logged_in_errors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `line` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request` varchar(10000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `non_technology_htmls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `non_technology_htmls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `non_technology_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `non_technology_htmls_question_id_unique` (`question_id`),
  CONSTRAINT `non_technology_htmls_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `not_yet_uploaded_canvas_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `not_yet_uploaded_canvas_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `image_src` varchar(3000) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Images not yet uploaded to ADAPT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `hours_until_due` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notifications_user_id_unique` (`user_id`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `null_lms_grade_passbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `null_lms_grade_passbacks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `numerical_migration_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `numerical_migration_backups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_table` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `old_qti_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_qti_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `numerical_migration_backups_source_table_source_id_index` (`source_table`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refresh_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oauth_providers_provider_provider_user_id_unique` (`provider`,`provider_user_id`),
  KEY `oauth_providers_user_id_foreign` (`user_id`),
  KEY `oauth_providers_provider_user_id_index` (`provider_user_id`),
  CONSTRAINT `oauth_providers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `old_discussion_media_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `old_discussion_media_uploads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `discussion_id` bigint(20) unsigned NOT NULL,
  `question_media_upload_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `old_text_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `old_text_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `old_webwork_paths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `old_webwork_paths` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `technology_id` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `opls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `webwork_path` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `webwork_code` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `original_assignment_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `original_assignment_scores` (
  `score_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `assignment_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_score` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_score` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  UNIQUE KEY `original_assignment_scores_score_id_unique` (`score_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `original_submission_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `original_submission_scores` (
  `submission_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `original_score` decimal(8,4) NOT NULL,
  `new_score` decimal(8,4) DEFAULT NULL,
  UNIQUE KEY `original_submission_scores_submission_id_unique` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `passback_by_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `passback_by_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `patient_informations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_informations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allergies` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dob` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight_units` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_weight` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bmi` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_bmi` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_application_of_updated_information` tinyint(3) unsigned DEFAULT NULL,
  `show_in_updated_information` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_informations_assignment_id_unique` (`assignment_id`),
  CONSTRAINT `patient_informations_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_course_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_course_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NOT NULL,
  `last_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pending_course_invitations_course_id_foreign` (`course_id`),
  KEY `pending_course_invitations_section_id_foreign` (`section_id`),
  CONSTRAINT `pending_course_invitations_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `pending_course_invitations_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_deleted_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_deleted_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `central_identity_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_mp4_conversions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_mp4_conversions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `environment` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_question_ownership_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_question_ownership_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `new_owner_user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pending_question_ownership_transfers_question_id_unique` (`question_id`),
  KEY `pending_question_ownership_transfers_new_owner_user_id_foreign` (`new_owner_user_id`),
  CONSTRAINT `pending_question_ownership_transfers_new_owner_user_id_foreign` FOREIGN KEY (`new_owner_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pending_question_ownership_transfers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_question_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_question_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_revision_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pending_question_revisions_assignment_id_question_id_unique` (`assignment_id`,`question_id`),
  KEY `pending_question_revisions_question_revision_id_foreign` (`question_revision_id`),
  KEY `pending_question_revisions_question_id_foreign` (`question_id`),
  CONSTRAINT `pending_question_revisions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `pending_question_revisions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `pending_question_revisions_question_revision_id_foreign` FOREIGN KEY (`question_revision_id`) REFERENCES `question_revisions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_transcriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_transcriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `upload_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `environment` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` varchar(10000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qti_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qti_imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `qti_job_id` bigint(20) unsigned NOT NULL,
  `identifier` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `xml` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `assignment_id` bigint(20) unsigned DEFAULT NULL,
  `question_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qti_imports_qti_job_id_identifier_unique` (`qti_job_id`,`identifier`),
  CONSTRAINT `qti_imports_qti_job_id_foreign` FOREIGN KEY (`qti_job_id`) REFERENCES `qti_jobs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qti_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qti_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `qti_source` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `assignment_template_id` bigint(20) unsigned DEFAULT NULL,
  `public` tinyint(3) unsigned NOT NULL,
  `folder_id` bigint(20) unsigned NOT NULL,
  `license` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_version` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qti_directory` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qti_jobs_qti_directory_unique` (`qti_directory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qti_json_before_fixing_select_choices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qti_json_before_fixing_select_choices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `qti_json` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'original qti json before fixing up the correct responses',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qti_json_before_fixing_select_choices_question_id_unique` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_authors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_better_titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_better_titles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_chapters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_chapters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_subject_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_editor_access_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_editor_access_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `access_code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_id_titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_id_titles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_learning_outcome`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_learning_outcome` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `learning_outcome_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_learning_outcome_unique` (`question_id`,`learning_outcome_id`),
  KEY `question_learning_outcome_learning_outcome_id_foreign` (`learning_outcome_id`),
  CONSTRAINT `question_learning_outcome_learning_outcome_id_foreign` FOREIGN KEY (`learning_outcome_id`) REFERENCES `learning_outcomes` (`id`),
  CONSTRAINT `question_learning_outcome_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_level_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_level_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `auto_graded` tinyint(1) NOT NULL,
  `open_ended` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_assignment_question_unique` (`user_id`,`assignment_id`,`question_id`),
  KEY `question_level_overrides_assignment_id_foreign` (`assignment_id`),
  KEY `question_level_overrides_question_id_foreign` (`question_id`),
  CONSTRAINT `question_level_overrides_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `question_level_overrides_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `question_level_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_media_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_media_uploads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_revision_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `original_filename` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint(20) unsigned NOT NULL,
  `s3_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `native_width` int(10) unsigned DEFAULT NULL,
  `native_height` int(10) unsigned DEFAULT NULL,
  `text` text COLLATE utf8mb4_unicode_ci,
  `order` smallint(5) unsigned DEFAULT NULL,
  `transcript` text COLLATE utf8mb4_unicode_ci,
  `show_captions` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_media_uploads_question_id_foreign` (`question_id`),
  CONSTRAINT `question_media_uploads_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_nas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_nas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `answer_html` text COLLATE utf8mb4_unicode_ci,
  `solution_html` text COLLATE utf8mb4_unicode_ci,
  `hint` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `text_question` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_nas_and_dollar_signs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_nas_and_dollar_signs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `answer_html` text COLLATE utf8mb4_unicode_ci,
  `solution_html` text COLLATE utf8mb4_unicode_ci,
  `hint` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `text_question` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_revision_propagations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_revision_propagations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_revision_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_question_ids` json DEFAULT NULL,
  `assignment_question_learning_tree_ids` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_revision_propagations_question_revision_id_foreign` (`question_revision_id`),
  KEY `question_revision_propagations_user_id_foreign` (`user_id`),
  CONSTRAINT `question_revision_propagations_question_revision_id_foreign` FOREIGN KEY (`question_revision_id`) REFERENCES `question_revisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `question_revision_propagations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revision_number` smallint(5) unsigned NOT NULL,
  `action` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_for_edit` text COLLATE utf8mb4_unicode_ci,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assessment',
  `page_id` bigint(20) unsigned NOT NULL,
  `library` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_subject_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_chapter_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_section_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(5000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technology_iframe` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachments` json DEFAULT NULL,
  `technology` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `open_ended_submission_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `qti_json` text COLLATE utf8mb4_unicode_ci,
  `qti_json_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webwork_code` text COLLATE utf8mb4_unicode_ci,
  `h5p_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `h5p_owner_imported` tinyint(3) unsigned DEFAULT NULL,
  `a11y_auto_graded_question_id` bigint(20) unsigned DEFAULT NULL,
  `non_technology` tinyint(1) NOT NULL DEFAULT '0',
  `non_technology_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `grading_style_id` smallint(5) unsigned DEFAULT NULL,
  `purpose` text COLLATE utf8mb4_unicode_ci,
  `text_question` text COLLATE utf8mb4_unicode_ci,
  `answer_html` text COLLATE utf8mb4_unicode_ci,
  `solution_html` text COLLATE utf8mb4_unicode_ci,
  `hint` text COLLATE utf8mb4_unicode_ci,
  `libretexts_link` varchar(1500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` mediumtext COLLATE utf8mb4_unicode_ci,
  `auto_attribution` tinyint(1) NOT NULL DEFAULT '1',
  `rubric` json DEFAULT NULL,
  `author` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_editor_user_id` bigint(20) unsigned DEFAULT NULL,
  `private_description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribution` text COLLATE utf8mb4_unicode_ci,
  `public` tinyint(1) NOT NULL DEFAULT '1',
  `clone_source_id` bigint(20) unsigned DEFAULT NULL,
  `forge_source_id` bigint(20) unsigned DEFAULT NULL,
  `cached` tinyint(1) NOT NULL DEFAULT '1',
  `version` smallint(5) unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_revisions_question_id_revision_number_unique` (`question_id`,`revision_number`),
  KEY `questions_primary_author_user_id_foreign` (`question_editor_user_id`),
  KEY `questions_folder_id_foreign` (`folder_id`),
  CONSTRAINT `question_revisions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_chapter_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_subject_chapter_section`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_subject_chapter_section` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_subject_id` bigint(20) unsigned DEFAULT NULL,
  `question_chapter_id` bigint(20) unsigned DEFAULT NULL,
  `question_section_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_tag` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_tag_question_id_tag_id_unique` (`question_id`,`tag_id`),
  KEY `question_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `question_tag_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `question_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_titles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `technology` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `library` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_titles_technology_technology_id_unique` (`technology`,`technology_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `version` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_webwork_macros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_webwork_macros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_revision_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `webwork_macro_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_question_revision_macro` (`question_id`,`question_revision_id`,`webwork_macro_id`),
  KEY `question_webwork_macros_webwork_macro_id_foreign` (`webwork_macro_id`),
  CONSTRAINT `question_webwork_macros_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `question_webwork_macros_webwork_macro_id_foreign` FOREIGN KEY (`webwork_macro_id`) REFERENCES `webwork_macros` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assessment',
  `page_id` bigint(20) unsigned NOT NULL,
  `library` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_subject_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_chapter_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_section_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(5000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technology_iframe` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachments` json DEFAULT NULL,
  `technology` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `open_ended_submission_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `qti_json` text COLLATE utf8mb4_unicode_ci,
  `qti_json_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webwork_code` text COLLATE utf8mb4_unicode_ci,
  `h5p_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `h5p_owner_imported` tinyint(3) unsigned DEFAULT NULL,
  `non_technology` tinyint(1) NOT NULL DEFAULT '0',
  `non_technology_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `grading_style_id` smallint(5) unsigned DEFAULT NULL,
  `purpose` text COLLATE utf8mb4_unicode_ci,
  `a11y_auto_graded_question_id` bigint(20) unsigned DEFAULT NULL,
  `text_question` text COLLATE utf8mb4_unicode_ci,
  `answer_html` text COLLATE utf8mb4_unicode_ci,
  `solution_html` text COLLATE utf8mb4_unicode_ci,
  `hint` text COLLATE utf8mb4_unicode_ci,
  `notes` mediumtext COLLATE utf8mb4_unicode_ci,
  `auto_attribution` tinyint(1) NOT NULL DEFAULT '1',
  `rubric` json DEFAULT NULL,
  `author` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_editor_user_id` bigint(20) unsigned DEFAULT NULL,
  `private_description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribution` text COLLATE utf8mb4_unicode_ci,
  `public` tinyint(1) NOT NULL DEFAULT '1',
  `clone_source_id` bigint(20) unsigned DEFAULT NULL,
  `forge_source_id` bigint(20) unsigned DEFAULT NULL,
  `cached` tinyint(1) NOT NULL DEFAULT '1',
  `version` smallint(5) unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `questions_page_id_library_copy_source_id_unique` (`page_id`,`library`,`clone_source_id`),
  KEY `questions_primary_author_user_id_foreign` (`question_editor_user_id`),
  KEY `questions_folder_id_foreign` (`folder_id`),
  KEY `questions_technology_id_index` (`technology_id`),
  KEY `questions_technology_technology_id_index` (`technology`,`technology_id`),
  KEY `questions_qti_json_type_index` (`qti_json_type`),
  KEY `questions_question_subject_id_index` (`question_subject_id`),
  KEY `questions_question_chapter_id_index` (`question_chapter_id`),
  KEY `questions_question_section_id_index` (`question_section_id`),
  CONSTRAINT `questions_folder_id_foreign` FOREIGN KEY (`folder_id`) REFERENCES `saved_questions_folders` (`id`),
  CONSTRAINT `questions_primary_author_user_id_foreign` FOREIGN KEY (`question_editor_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questions_before_adding_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions_before_adding_authors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assessment',
  `page_id` bigint(20) unsigned NOT NULL,
  `library` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technology_iframe` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `technology` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a11y_technology` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a11y_technology_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_technology` tinyint(1) NOT NULL DEFAULT '0',
  `text_question` text COLLATE utf8mb4_unicode_ci,
  `answer_html` text COLLATE utf8mb4_unicode_ci,
  `solution_html` text COLLATE utf8mb4_unicode_ci,
  `hint` text COLLATE utf8mb4_unicode_ci,
  `libretexts_link` varchar(1500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `auto_attribution` tinyint(1) NOT NULL DEFAULT '1',
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_editor_user_id` bigint(20) unsigned DEFAULT NULL,
  `private_description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribution` text COLLATE utf8mb4_unicode_ci,
  `public` tinyint(1) NOT NULL DEFAULT '1',
  `cached` tinyint(1) NOT NULL DEFAULT '1',
  `version` smallint(5) unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `questions_page_id_library_unique` (`page_id`,`library`),
  KEY `questions_primary_author_user_id_foreign` (`question_editor_user_id`),
  KEY `questions_folder_id_foreign` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questions_before_migration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions_before_migration` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assessment',
  `page_id` bigint(20) unsigned NOT NULL,
  `library` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technology_iframe` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `technology` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qti_json` text COLLATE utf8mb4_unicode_ci,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webwork_code` text COLLATE utf8mb4_unicode_ci,
  `h5p_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `h5p_owner_imported` tinyint(3) unsigned DEFAULT NULL,
  `a11y_technology` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a11y_technology_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_technology` tinyint(1) NOT NULL DEFAULT '0',
  `non_technology_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `text_question` text COLLATE utf8mb4_unicode_ci,
  `answer_html` text COLLATE utf8mb4_unicode_ci,
  `solution_html` text COLLATE utf8mb4_unicode_ci,
  `hint` text COLLATE utf8mb4_unicode_ci,
  `libretexts_link` varchar(1500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `auto_attribution` tinyint(1) NOT NULL DEFAULT '1',
  `author` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_editor_user_id` bigint(20) unsigned DEFAULT NULL,
  `private_description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribution` text COLLATE utf8mb4_unicode_ci,
  `public` tinyint(1) NOT NULL DEFAULT '1',
  `copy_source_id` bigint(20) unsigned DEFAULT NULL,
  `cached` tinyint(1) NOT NULL DEFAULT '1',
  `version` smallint(5) unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `questions_page_id_library_copy_source_id_unique` (`page_id`,`library`,`copy_source_id`),
  KEY `questions_primary_author_user_id_foreign` (`question_editor_user_id`),
  KEY `questions_folder_id_foreign` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `random_leftover_webwork_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `random_leftover_webwork_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `technology_iframe` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `random_leftover_webwork_questions_question_id_unique` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `randomized_assignment_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `randomized_assignment_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment_question_user` (`assignment_id`,`question_id`,`user_id`),
  KEY `randomized_assignment_questions_question_id_foreign` (`question_id`),
  KEY `randomized_assignment_questions_user_id_foreign` (`user_id`),
  CONSTRAINT `randomized_assignment_questions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `randomized_assignment_questions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `randomized_assignment_questions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `refresh_question_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refresh_question_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `nature_of_update` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `refresh_question_requests_user_id_question_id_status_unique` (`user_id`,`question_id`,`status`),
  KEY `refresh_question_requests_question_id_foreign` (`question_id`),
  CONSTRAINT `refresh_question_requests_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `refresh_question_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_assignment_contacted_instructors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `release_assignment_contacted_instructors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_assignment_id_unique` (`user_id`,`assignment_id`),
  KEY `release_assignment_contacted_instructors_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `release_assignment_contacted_instructors_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `release_assignment_contacted_instructors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `remediation_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remediation_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `learning_tree_id` bigint(20) unsigned NOT NULL,
  `branch_id` tinyint(3) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci,
  `proportion_correct` decimal(8,4) DEFAULT NULL,
  `submission_count` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `remediation_submission_unique` (`user_id`,`assignment_id`,`learning_tree_id`,`branch_id`,`question_id`),
  KEY `remediation_submissions_learning_tree_id_foreign` (`learning_tree_id`),
  KEY `remediation_submissions_assignment_id_foreign` (`assignment_id`),
  KEY `remediation_submissions_question_id_foreign` (`question_id`),
  CONSTRAINT `remediation_submissions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `remediation_submissions_learning_tree_id_foreign` FOREIGN KEY (`learning_tree_id`) REFERENCES `learning_trees` (`id`),
  CONSTRAINT `remediation_submissions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `remediation_submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `remove_plain_text_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remove_plain_text_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `open_ended_text_editor` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_open_ended_text_editor` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_open_ended_submission_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_toggles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_toggles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `section_scores` tinyint(1) NOT NULL DEFAULT '0',
  `comments` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `criteria` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_toggles_assignment_id_foreign` (`assignment_id`),
  KEY `report_toggles_question_id_foreign` (`question_id`),
  CONSTRAINT `report_toggles_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `report_toggles_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `review_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `session_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_histories_user_id_foreign` (`user_id`),
  KEY `review_histories_assignment_id_foreign` (`assignment_id`),
  KEY `review_histories_question_id_foreign` (`question_id`),
  KEY `review_histories_session_id_index` (`session_id`),
  CONSTRAINT `review_histories_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `review_histories_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `review_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rubric_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_revision_id` bigint(20) unsigned NOT NULL,
  `category` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `criteria` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` smallint(5) unsigned NOT NULL,
  `order` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rubric_categories_question_id_foreign` (`question_id`),
  CONSTRAINT `rubric_categories_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rubric_category_criteria_pendings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_category_criteria_pendings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `rubric_category_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `notify_user_id` bigint(20) unsigned NOT NULL,
  `processed` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rubric_category_criteria_pendings_assignment_id_foreign` (`assignment_id`),
  KEY `rubric_category_criteria_pendings_rubric_category_id_foreign` (`rubric_category_id`),
  KEY `rubric_category_criteria_pendings_user_id_foreign` (`user_id`),
  KEY `rubric_category_criteria_pendings_notify_user_id_foreign` (`notify_user_id`),
  CONSTRAINT `rubric_category_criteria_pendings_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `rubric_category_criteria_pendings_notify_user_id_foreign` FOREIGN KEY (`notify_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `rubric_category_criteria_pendings_rubric_category_id_foreign` FOREIGN KEY (`rubric_category_id`) REFERENCES `rubric_categories` (`id`),
  CONSTRAINT `rubric_category_criteria_pendings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rubric_category_custom_criteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_category_custom_criteria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `rubric_category_id` bigint(20) unsigned NOT NULL,
  `custom_criteria` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rccc-assignment_foreign_id` (`assignment_id`),
  KEY `rccc-rubric_category_foreign_id` (`rubric_category_id`),
  CONSTRAINT `rccc-assignment_foreign_id` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `rccc-rubric_category_foreign_id` FOREIGN KEY (`rubric_category_id`) REFERENCES `rubric_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rubric_category_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_category_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `rubric_category_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `message` text COLLATE utf8mb4_unicode_ci,
  `custom_feedback` text COLLATE utf8mb4_unicode_ci,
  `custom_score` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rubric_category_submissions_rubric_category_id_foreign` (`rubric_category_id`),
  KEY `rubric_category_submissions_user_id_foreign` (`user_id`),
  KEY `rubric_category_submissions_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `rubric_category_submissions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `rubric_category_submissions_rubric_category_id_foreign` FOREIGN KEY (`rubric_category_id`) REFERENCES `rubric_categories` (`id`),
  CONSTRAINT `rubric_category_submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rubric_criteria_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_criteria_tests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `criteria` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rubric_points_breakdowns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_points_breakdowns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `points_breakdown` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rubric_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(5000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rubric` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rubric_templates_user_id_foreign` (`user_id`),
  CONSTRAINT `rubric_templates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s3_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `s3_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `s3_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filesize` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'MB',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `saved_questions_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saved_questions_folders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `saved_questions_folders_user_id_foreign` (`user_id`),
  CONSTRAINT `saved_questions_folders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schools_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `score` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scores_user_id_assignment_id_unique` (`user_id`,`assignment_id`),
  KEY `scores_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `scores_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `scores_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `crn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `access_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sections_course_id_foreign` (`course_id`),
  CONSTRAINT `sections_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seeds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `seed` varchar(10000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seeds_assignment_id_question_id_user_id_unique` (`assignment_id`,`question_id`,`user_id`),
  KEY `seeds_user_id_foreign` (`user_id`),
  KEY `seeds_question_id_foreign` (`question_id`),
  CONSTRAINT `seeds_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `seeds_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `seeds_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `show_scores_released_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `show_scores_released_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `previous_show_scores` tinyint(3) unsigned NOT NULL,
  `auto_release_changed` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `previous_auto_release_show_scores` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_auto_release_show_scores_after` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_auto_release_show_scores_activated` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `show_scores_released_assignments_assignment_id_unique` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shown_hints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shown_hints` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shown_hints_unique` (`user_id`,`assignment_id`,`question_id`),
  KEY `shown_hints_assignment_id_foreign` (`assignment_id`),
  KEY `shown_hints_question_id_foreign` (`question_id`),
  CONSTRAINT `shown_hints_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `shown_hints_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `shown_hints_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `site_maps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_maps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `solutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solutions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'q=question, a=assignment',
  `assignment_id` bigint(20) unsigned DEFAULT NULL,
  `question_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `solutions_user_id_question_id_unique` (`user_id`,`question_id`),
  KEY `solutions_question_id_foreign` (`question_id`),
  CONSTRAINT `solutions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `structures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `s3_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `structures_assignment_id_foreign` (`assignment_id`),
  KEY `structures_question_id_foreign` (`question_id`),
  CONSTRAINT `structures_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `structures_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_emails_with_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_emails_with_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructor_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructor_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submission_confirmations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_confirmations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submission_file_conversions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_file_conversions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `submission` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `number_of_pages` smallint(5) unsigned DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `submission_file_conversions_assignment_id_submission_unique` (`assignment_id`,`submission`),
  CONSTRAINT `submission_file_conversions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submission_file_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_file_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `submission` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page` smallint(5) unsigned NOT NULL,
  `s3_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `width` smallint(5) unsigned DEFAULT NULL,
  `height` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `submission_file_pages_assignment_id_submission_page_unique` (`assignment_id`,`submission`,`page`),
  CONSTRAINT `submission_file_pages_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submission_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'q=question file, audio, text, discuss_it',
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned DEFAULT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `pasted_content` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `page` smallint(5) unsigned DEFAULT NULL,
  `end_page` smallint(5) unsigned DEFAULT NULL,
  `number_of_pages` smallint(5) unsigned DEFAULT NULL,
  `date_submitted` datetime NOT NULL,
  `file_feedback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_feedback` longtext COLLATE utf8mb4_unicode_ci,
  `text_feedback_editor` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_graded` datetime DEFAULT NULL,
  `grader_id` bigint(20) unsigned DEFAULT NULL,
  `score` decimal(8,4) DEFAULT NULL,
  `applied_late_penalty` smallint(5) unsigned DEFAULT NULL,
  `upload_count` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `submission_files_user_id_foreign` (`user_id`),
  KEY `submission_files_assignment_id_foreign` (`assignment_id`),
  CONSTRAINT `submission_files_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `submission_files_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submission_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `submission_histories_user_id_foreign` (`user_id`),
  KEY `submission_histories_assignment_id_foreign` (`assignment_id`),
  KEY `submission_histories_question_id_foreign` (`question_id`),
  CONSTRAINT `submission_histories_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `submission_histories_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `submission_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submission_page_annotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_page_annotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `page` smallint(5) unsigned NOT NULL,
  `state` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `grader_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `submission_page_annotations_unique` (`assignment_id`,`question_id`,`user_id`,`page`),
  KEY `submission_page_annotations_question_id_foreign` (`question_id`),
  KEY `submission_page_annotations_user_id_foreign` (`user_id`),
  KEY `submission_page_annotations_grader_id_foreign` (`grader_id`),
  CONSTRAINT `submission_page_annotations_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submission_page_annotations_grader_id_foreign` FOREIGN KEY (`grader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `submission_page_annotations_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submission_page_annotations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submission_score_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_score_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `score` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `submission_score_overrides_user_id_foreign` (`user_id`),
  KEY `submission_score_overrides_assignment_id_foreign` (`assignment_id`),
  KEY `submission_score_overrides_question_id_foreign` (`question_id`),
  CONSTRAINT `submission_score_overrides_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `submission_score_overrides_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `submission_score_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submission_texts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_texts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `submitted_work` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_work_at` timestamp NULL DEFAULT NULL,
  `score` decimal(8,4) NOT NULL COMMENT 'The computed score based on whether it was correct and any learning tree/late penalty deductions.',
  `submission_count` smallint(5) unsigned NOT NULL,
  `show_solution` tinyint(1) NOT NULL DEFAULT '0',
  `answered_correctly_at_least_once` tinyint(1) NOT NULL,
  `time_on_task` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'in seconds',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `submissions_user_id_assignment_id_question_id_unique` (`user_id`,`assignment_id`,`question_id`),
  KEY `submissions_assignment_id_foreign` (`assignment_id`),
  KEY `submissions_question_id_foreign` (`question_id`),
  KEY `submissions_submitted_work_index` (`submitted_work`),
  CONSTRAINT `submissions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `submissions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submissions_recomputed_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submissions_recomputed_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` bigint(20) unsigned NOT NULL,
  `score` decimal(8,4) NOT NULL,
  `original_score` decimal(8,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `submissions_recomputed_scores_submission_id_foreign` (`submission_id`),
  CONSTRAINT `submissions_recomputed_scores_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submitted_work_pending_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submitted_work_pending_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `score` decimal(8,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `submitted_work_pending_scores_assignment_id_foreign` (`assignment_id`),
  KEY `submitted_work_pending_scores_question_id_foreign` (`question_id`),
  KEY `submitted_work_pending_scores_user_id_foreign` (`user_id`),
  CONSTRAINT `submitted_work_pending_scores_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `submitted_work_pending_scores_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `submitted_work_pending_scores_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submitted_works`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submitted_works` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `submitted_work` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `submitted_works_user_id_assignment_id_question_id_unique` (`user_id`,`assignment_id`,`question_id`),
  KEY `submitted_works_assignment_id_foreign` (`assignment_id`),
  KEY `submitted_works_question_id_foreign` (`question_id`),
  CONSTRAINT `submitted_works_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `submitted_works_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `submitted_works_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `table_grader_notification_reminders_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `table_grader_notification_reminders_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tester_access_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tester_access_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `access_code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tester_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tester_courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tester_courses_user_id_course_id_unique` (`user_id`,`course_id`),
  KEY `tester_courses_course_id_foreign` (`course_id`),
  CONSTRAINT `tester_courses_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `tester_courses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tester_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tester_students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tester_user_id` bigint(20) unsigned NOT NULL,
  `student_user_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tester_students_tester_user_id_student_user_id_unique` (`tester_user_id`,`student_user_id`),
  KEY `tester_students_student_user_id_foreign` (`student_user_id`),
  KEY `tester_students_section_id_foreign` (`section_id`),
  CONSTRAINT `tester_students_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `tester_students_student_user_id_foreign` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `tester_students_tester_user_id_foreign` FOREIGN KEY (`tester_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `textbook_urls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `textbook_urls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `data_shop_id` bigint(20) unsigned NOT NULL,
  `textbook_url` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `throttles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `throttles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `time_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `time_zones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `time_zones_value_unique` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `titles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `library` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unconfirmed_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `unconfirmed_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `submission` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unconfirmed_submissions_user_id_assignment_id_question_id_unique` (`user_id`,`assignment_id`,`question_id`),
  KEY `unconfirmed_submissions_assignment_id_foreign` (`assignment_id`),
  KEY `unconfirmed_submissions_question_id_foreign` (`question_id`),
  CONSTRAINT `unconfirmed_submissions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  CONSTRAINT `unconfirmed_submissions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `unconfirmed_submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `central_identity_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` smallint(5) unsigned NOT NULL COMMENT '1=admin, 2=instructor, 3=student, 4=ta',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_zone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructor_user_id` bigint(20) unsigned DEFAULT NULL,
  `fake_student` tinyint(1) NOT NULL DEFAULT '0',
  `formative_student` tinyint(1) NOT NULL DEFAULT '0',
  `testing_student` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `lms_user_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verify_status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `final_deletion_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_lms_user_id_index` (`lms_user_id`),
  KEY `users_instructor_user_id_index` (`instructor_user_id`),
  KEY `users_central_identity_id_index` (`central_identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users_with_no_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_with_no_role` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_zone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `width` smallint(5) unsigned DEFAULT NULL,
  `height` smallint(5) unsigned DEFAULT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_revision_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_by_question_id_revision_id` (`filename`,`question_id`,`question_revision_id`),
  KEY `webwork_attachments_question_id_foreign` (`question_id`),
  CONSTRAINT `webwork_attachments_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_image_sizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_image_sizes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webwork_attachment_id` bigint(20) unsigned NOT NULL,
  `width` smallint(5) unsigned NOT NULL,
  `height` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_long_radio_buttons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_long_radio_buttons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `old_webwork_code` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_webwork_code` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webwork_long_radio_buttons_question_id_unique` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_macro_co_editors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_macro_co_editors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webwork_macro_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webwork_macro_co_editors_webwork_macro_id_user_id_unique` (`webwork_macro_id`,`user_id`),
  KEY `webwork_macro_co_editors_user_id_foreign` (`user_id`),
  CONSTRAINT `webwork_macro_co_editors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `webwork_macro_co_editors_webwork_macro_id_foreign` FOREIGN KEY (`webwork_macro_id`) REFERENCES `webwork_macros` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_macro_editors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_macro_editors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `granted_by_user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webwork_macro_editors_user_id_unique` (`user_id`),
  KEY `webwork_macro_editors_granted_by_user_id_foreign` (`granted_by_user_id`),
  CONSTRAINT `webwork_macro_editors_granted_by_user_id_foreign` FOREIGN KEY (`granted_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `webwork_macro_editors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_macro_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_macro_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webwork_macro_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `macro` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `edited_by_user_id` bigint(20) unsigned NOT NULL,
  `revision_number` int(10) unsigned NOT NULL DEFAULT '0',
  `reason_for_edit` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webwork_macro_revisions_webwork_macro_id_foreign` (`webwork_macro_id`),
  KEY `webwork_macro_revisions_edited_by_user_id_foreign` (`edited_by_user_id`),
  CONSTRAINT `webwork_macro_revisions_edited_by_user_id_foreign` FOREIGN KEY (`edited_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `webwork_macro_revisions_webwork_macro_id_foreign` FOREIGN KEY (`webwork_macro_id`) REFERENCES `webwork_macros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_macros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_macros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `macro` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_retired` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webwork_macros_user_id_foreign` (`user_id`),
  CONSTRAINT `webwork_macros_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_private_to_natives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_private_to_natives` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `original_path` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `webwork_code` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_regex_chapter_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_regex_chapter_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `chapter_id` bigint(20) unsigned DEFAULT NULL,
  `before_regex` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `after_regex` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_regex_section_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_regex_section_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `before_regex` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `after_regex` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_regex_subject_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_regex_subject_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `before_regex` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `after_regex` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webwork_submission_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webwork_submission_errors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `first_error` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'For a quick check --- there may be others in the submission',
  `problem_jwt` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whitelisted_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whitelisted_domains` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `whitelisted_domain` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whitelisted_domains_course_id_foreign` (`course_id`),
  CONSTRAINT `whitelisted_domains_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zihao_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zihao_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `technology_iframe` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `technology_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zihao_questions_question_id_unique` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2017_12_07_122845_create_oauth_providers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2020_06_23_200012_create_courses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2020_06_24_131635_create_assignments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2020_06_30_203016_create_enrollments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2020_07_01_152311_create_scores_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2020_07_03_163529_create_course_access_codes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2020_07_12_202327_create_questions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2020_07_12_202507_create_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2020_07_12_203725_create_question_tag_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2020_07_13_203141_create_assignment_question_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2020_07_17_002004_create_results_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2020_07_17_194615_create_instructor_access_codes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2020_07_24_192613_create_submissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2020_07_28_222833_create_extensions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2020_08_09_201158_create_learning_trees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2020_08_25_184652_create_ta_access_codes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2020_08_25_202112_update_role_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2020_08_25_222609_create_graders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2020_08_26_175326_update_role_to_users_2',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2020_08_26_190441_rename_ta_access_codes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2020_08_27_134845_add_question_files_to_assignment_question',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2020_08_28_015847_create_submission_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2020_08_29_153133_create_site_maps_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2020_08_29_221105_update_technology_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2020_08_31_134552_add_location_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2020_08_31_220030_rename_technology_id_column_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2020_09_01_143221_update_location_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2020_09_01_174121_create_mind_touch_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2020_09_01_201235_update_page_id_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2020_09_02_030045_update_page_id_event_time_to_mind_touch_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2020_09_03_132648_add_default_points_per_question_to_assignments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2020_09_03_163527_add_score_to_submissions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2020_09_03_164414_update_score_to_submission_files',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2020_09_03_164620_add_points_to_assignment_question',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2020_09_03_171751_update_created_at_updated_at_to_assignment_question',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2020_09_03_173512_update_points_to_assignment_question',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2020_09_06_130556_update_index_to_mind_touch_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2020_09_07_125832_add_scoring_type_to_assignments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2020_09_07_131238_update_default_points_per_question_to_assignments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2020_09_08_234410_add_contents_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2020_09_09_134914_update_contents_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2020_09_09_135914_update_contents_as_body_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2020_09_10_141644_update_technology_as_string_to_questions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2020_09_10_200342_update_scoring_type_and_submission_files_to_assignments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2020_09_10_200842_update_type_as_string_to_submission_files',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2020_09_12_162004_add_time_zone_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2020_09_12_170407_update_default_points_per_question_as_nullable_to_assignments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2020_09_16_133258_add_solutions_shown_to_assignments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2020_09_17_153344_add_upload_count_to_submission_files',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2020_09_27_194040_update_assignments_to_submissions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2020_09_30_210552_add_source_to_assignments',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2020_10_05_131632_add_non_technology_to_questions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2020_10_05_133029_update_body_to_questions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2020_10_06_120855_drop_author_title_location_to_questions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2020_10_11_203724_create_solutions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2020_10_17_163036_update_type_to_submission_files',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2020_10_17_201112_create_cutups_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2020_10_17_214723_update_assignment_to_solutions',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2020_10_23_130937_add_scores_released_to_assignments',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2020_10_26_124632_create_seeds_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2020_10_26_173038_add_grader_id_to_submission_files',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2020_11_06_212011_update_students_can_view_assignment_statistics_to_assignments',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2020_11_08_184610_create_assignment_groups_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2020_11_08_204315_add_assignment_group_id_to_assignments',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2020_11_08_233844_create_assignment_group_weights_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2020_11_09_211551_update_students_can_view_weighted_average_to_courses',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2020_11_10_233648_update_assignment_group_id_course_id_to_assignment_group_weights',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2020_11_11_191306_add_external_source_points_to_assignments_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2020_11_13_141308_add_include_in_weighted_average_to_assignments',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2020_11_17_140510_update_assignment_group_id_to_assignments',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2020_11_19_162437_add_instructions_to_assignments',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2020_11_27_001843_create_letter_grades_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2020_11_27_180809_add_round_scores_to_letter_grades_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2020_11_27_182521_update_letter_grades_to_letter_grades_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2020_11_27_231528_add_students_can_view_letter_grades_to_letter_grades',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2020_11_28_195819_update_letter_grades_not_nullable_to_letter_grades_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2020_11_28_210130_rename_letter_grades',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2020_12_02_143614_add_columns_to_learning_trees',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2020_12_02_174948_drop_question_id_to_learning_trees',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2020_12_04_222516_create_logs_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2020_12_10_181409_add_viewable_to_assignments',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2020_12_15_141709_add_assessment_type_to_assignments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2020_12_15_223111_add_learning_tree_assignment_columns_to_assignments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2020_12_16_145334_create_assignment_question_learning_tree_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2020_12_16_151755_add_submission_count_to_submissions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2020_12_16_183033_add_root_node_page_id_to_learning_trees',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2020_12_17_180729_add_show_points_per_question_to_assignments',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2020_12_18_134457_add_late_policy_columns_to_assignments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2020_12_18_153844_update_late_policy_and_submission_files_to_assignments',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2020_12_18_181230_update_percent_decay_to_assignments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2020_12_18_182538_upadte_assessment_type_to_assignments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2020_12_18_190254_add_explored_learning_tree_to_submissions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2020_12_18_222704_add_learning_tree_points_to_submissions',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2020_12_18_224536_update_percent_earned_for_entering_learning_tree_to_assignments',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2020_12_20_005733_update_percent_decrease_to_assignments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2020_12_20_013746_update_learning_tree_points_to_submissions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2020_12_20_140115_rename_learning_tree_percent_decrease_to_assignments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2020_12_20_232502_drop_learning_tree_exploration_points_to_submissions',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2020_12_21_001252_add_answered_correclty_to_submissions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2020_12_21_174930_update_answered_correctly_to_submissions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2020_12_23_152241_add_late_policy_deadine_to_assignments',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2020_12_23_212336_update_late_policy_to_assignments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2020_12_24_154644_create_extra_credits_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2020_12_25_153347_udpate_score_to_submissions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2021_01_02_142547_add_root_node_library_to_learning_trees',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2021_01_02_143255_add_library_to_questions_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2021_01_07_121921_create_submission_texts_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2021_01_07_125027_add_submission_texts_to_assignments_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2021_01_07_130438_add_open_ended_submission_type_to_assignment_question',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2021_01_07_132829_add_default_open_ended_submission_to_assignment_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2021_01_07_135436_update_default_open_ended_submission_type_to_assignments',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2021_01_07_135653_update_default_open_ended_submission_type_v2_to_assignments',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2021_01_07_214242_add_grader_id_to_submission_texts_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2021_01_07_224544_drop_submission_texts_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2021_01_13_222502_update_text_and_type_to_submission_files',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2021_01_16_224938_update_shown_to_courses',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2021_01_17_222321_add_can_submit_and_can_view__to_assignment_question_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2021_01_20_120438_add_clicker_results_released_to_assignment_question',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2021_01_22_224347_add_text_to_solutions',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2021_01_25_133359_add_clicker_start_end_to_assignment_question',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2021_01_26_014737_drop_question_page_index_add_page_library_index_to_questions',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2021_01_26_015600_drop_questions_technology_id_technology_unique_to_questions',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2021_01_26_022712_drop_clicker_columns_to_assignment_question',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2021_01_27_014843_add_order_to_assignment_question',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2021_01_27_135357_add_title_to_questions',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2021_01_27_174307_change_late_policy_deadline_to_assignments',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2021_01_27_232952_create_notifications_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2021_01_28_222047_add_default_clicker_time_to_assignments',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2021_01_29_130108_update_default_clicker_time_t0o_submit_to_assignments',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2021_01_29_151140_update_submission_for_longer_text_to_submission_files',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2021_01_31_150748_add_order_to_assignments',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2021_02_03_141917_add_public_to_courses',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2021_02_07_200949_create_titles_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2021_02_08_225119_add_notifications_to_assignments',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2021_02_14_171325_create_old_text_submissions',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2021_02_15_220111_add_default_open_ended_text_editor_to_assignments',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2021_02_15_220818_add_open_ended_text_editor_to_assignment_question',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2021_02_16_142604_add_default_open_ended_text_to_assignment_question',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2021_02_21_144630_update_instructions_to_assignments',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2020_12_06_144746_create_lti_keys_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2020_12_06_145012_create_lti_registrations_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2020_12_06_145540_create_lti_deployments_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2020_12_06_161059_drop_kid_to_lti_keys',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2020_12_06_161126_add_kid_to_lti_registrations',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2021_02_19_160336_add_lms_resource_link_it_to_assignments',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2021_02_19_192344_create_lti_launches_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2021_02_21_182604_add_launch_and_assignment_id_to_launches',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2021_02_21_184905_add_default_timestamps_to_lti_launches',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2021_02_22_120353_create_lti_gradepassbacks_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2021_02_22_122105_drop_launch_to_lti_launches',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2021_02_23_133423_add_show_z_scores_to_courses',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2021_02_25_172952_create_data_shops_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2021_02_25_215342_drop_logs_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2021_03_01_153259_add_author_license_attribution_to_questions',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2021_03_03_140733_create_sections_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2021_03_03_143540_add_section_id_to_enrollments',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2021_03_03_223830_add_access_code_to_sections',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2021_03_04_152845_drop_course_access_codes_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2021_03_04_192719_add_section_id_remove_course_id_to_grader_access_codes',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2021_03_04_225523_add_section_id_to_graders',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2021_03_06_225652_update_fake_student_to_users',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2021_03_10_210925_create_canned_responses_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2021_03_10_231145_add_text_feedback_editor_to_submission_files',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2021_03_17_114900_create_assign_to_timings_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2021_03_17_115333_create_assign_to_groups_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2021_03_17_120247_create_assign_to_users_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2021_03_17_120857_move_assignment_info_to_assign_tos',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2021_03_17_123913_drop_timings_to_assignments',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2021_03_18_162147_drop_final_submission_deadline_to_assignments',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2021_03_28_225250_add_primary_key_to_lti_deployments',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2021_03_28_230513_add_primary_key_to_password_resets',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2021_03_30_185647_add_number_of_randomized_assessments_to_assignments_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2021_03_30_213732_create_randomized_assignment_questions_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2021_05_03_180938_update_page_to_submission_files',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2021_05_07_142446_create_assignment_grader_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2021_05_12_164605_update_access_level_to_assignment_grader',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2021_05_12_221005_rename_assignment_grader_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2021_05_13_213655_truncate_assignmnent_grader_access_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2021_05_19_194005_create_no_student_access_codes_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2021_05_20_222818_create_schools_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2021_05_21_131514_add_school_id_to_courses',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2021_05_14_151404_create_head_graders_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2021_05_15_180337_create_grader_notifications_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2021_05_17_192100_create_table_grader_notification_reminders_log',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2021_05_18_011728_update_grading_reminder_time_period_to_grader_notifications',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2021_05_25_193651_create_learning_tree_histories_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2021_05_27_191853_update_combined_pdf_to_assignments',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2021_06_11_220555_update_library_and_page_id_to_learning_tree_histories',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2021_06_22_183453_update_student_id_to_users',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2021_06_22_195650_update_crn_to_sections',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2021_06_22_212101_update_term_to_courses',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2021_07_06_221516_update_attribution_to_questions',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2021_07_07_173801_update_descriptions_to_courses',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2021_07_07_203217_update_descriptions_to_assignments',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2021_07_07_212327_update_private_description_to_questions',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2021_07_09_201257_update_license_version_to_questions',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2021_07_10_181102_update_attribution_type_to_questions',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2021_07_14_124637_update_alpha_to_courses',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2021_07_14_125042_create_beta_courses_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2021_07_14_125559_create_beta_assignments_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2021_07_21_003347_create_alpha_course_import_codes',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2021_07_21_164406_create_beta_course_approvals_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2021_07_23_124449_beta_approval_notifications_to_courses',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2021_07_23_220955_update_learning_tree_id_to_beta_course_approvals',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2021_08_04_182714_drop_alpha_course_import_codes_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2021_08_07_125826_update_toggles_to_assignment_question',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2021_08_10_131050_create_refresh_question_requests_table',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2021_08_16_204954_update_lms_to_courses',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2021_08_23_122338_update_libretexts_url_to_assignments',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2021_08_26_133549_update_score_to_lti_grade_passbacks',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2021_08_28_165138_update_success_to_lti_grade_passbacks',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2021_08_30_181113_update_input_to_datashops',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2021_08_31_120811_add_anonymous_users_to_courses',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2021_09_07_220002_create_saved_questions_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2021_09_16_201125_update_graders_can_see_student_names_to_assignments',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2021_09_20_180427_update_order_to_courses',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2021_09_21_182435_update_due_date_to_data_shops',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2021_09_30_143520_create_lti_caches_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2021_09_30_183506_update_jwt_body_to_lti_launches',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2021_09_30_184826_drop_lti_caches_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2021_10_15_133952_create_question_level_overrides_table',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2021_10_15_142246_create_compiled_pdf_overrides_table',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2021_10_12_194601_update_default_completion_scoring_mode_to_assignments',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2021_10_21_170358_create_assignment_level_overrides_table',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2021_10_22_194303_update_points_and_scoring_type_to_data_shops',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2021_10_27_015412_update_iss_to_lti_registrations',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2021_10_28_125409_add_campus_id_to_lti_registrations',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2021_10_31_131142_update_admin_info_to_lti_registrations',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2021_10_31_220903_create_lti_schools_table',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2021_11_03_165744_update_special_classes_to_questions',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2021_11_08_125531_update_iss_not_unique_to_lti_registrations',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2021_11_08_174547_add_answer_html_to_questions',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2021_11_09_141534_update_url_to_data_shops',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2021_11_09_154514_update_url_to_questions',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2021_11_15_135807_update_private_license_fixed_to_questions',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2021_11_16_144006_remove_private_license_fixed_to_questions',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2021_11_17_170258_update_cached_to_questions',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2021_11_29_131729_update_libretexts_link_to_questions',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2021_11_29_170545_update_assignment_question_detail_to_saved_questions',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2021_12_06_221129_drop_assignment_question_id_to_saved_questions',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2021_11_22_142834_update_technology_id_to_questions',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2021_11_22_201308_update_primary_author_user_id_to_questions',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2021_11_30_134702_create_question_editor_access_codes_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2021_11_30_175151_update_role_with_question_editor_to_users',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2021_12_02_211029_update_change_column_primary_author_user_id_to_questions',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2021_12_04_171149_update_public_to_questions',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2021_12_11_152441_update_question_type_to_questions',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2021_12_12_182431_create_branches_table',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2022_01_01_191713_create_saved_questions_folders_table',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2022_01_01_191750_update_folder_id_to_saved_questions',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2022_01_01_193329_update_folder_as_foreign_key_to_saved_questions',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2022_01_05_135907_update_type_to_saved_questions_folders',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2022_01_05_142637_update_folder_id_to_questions',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2022_01_05_144317_update_folder_id_for_current_questions_to_questions',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2022_01_05_152846_update_saved_questions_to_my_favorites',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2022_01_18_135103_update_number_of_allowed_attempts_to_assignments',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2022_01_18_135320_make_number_of_allowed_attempts_1_to_assignments',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2022_01_18_144850_update_real_time_number_of_attempts_penalty_to_assignments',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2022_01_19_211205_update_show_solution_to_submissions',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2022_01_20_134601_update_solutions_availability_to_assignments',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2022_01_20_135129_update_solutions_availability_for_previous_real_time_to_assignments',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2022_01_20_225144_update_number_of_allowed_attempts_penalty_to_0_to_assignments',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2022_01_22_141554_update_points_per_question_changes_to_assignments',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2022_01_22_210601_update_weight_to_assignment_question',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2022_01_23_224047_update_decimal_precision_of_points_weight_to_assignment_question',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2022_01_28_141520_update_provider_user_id_provider_unique_to_oauth_providers',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2022_01_28_224435_update_score_rounding_to_submissions',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2022_01_31_220540_update_score_rounding_to_submission_files',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2022_02_05_135048_create_can_give_ups_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2022_02_05_233213_update_foreign_keys_to_can_give_ups',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2022_02_04_231002_create_assignment_topics_table',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2022_02_07_205248_update_topic_id_to_assignment_question',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2022_02_16_173733_update_a11y_to_enrollments',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2022_02_18_142118_update_a11y_to_questions',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2022_02_27_214233_update_version_to_questions',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2022_02_28_013119_create_question_versions_table',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2022_03_01_144336_create_question_titles_table',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2022_03_03_134917_update_library_page_id_to_question_titles',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2022_03_03_140956_create_question_id_titles_table',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2022_03_04_135849_create_question_authors_table',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2022_03_04_224202_create_question_better_titles_table',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2022_03_20_132940_update_hints_and_hints_penalty_to_assignments',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2022_03_21_124919_update_default_learning_tree_to_assignment_question_learning_tree',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2022_03_22_130918_create_remediation_submissions_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (288,'2022_03_23_161717_update_learning_tree_success_criteria_satisfied_to_submissions',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2022_03_23_211447_create_shown_hints_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2022_03_26_183058_create_learning_tree_time_lefts',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2022_03_29_201601_update_question_numbers_shown_in_iframe_to_courses',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2022_04_01_175657_create_lti_tokens_table',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2022_04_05_130400_update_number_of_resets_to_assignments',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2022_04_05_130637_update_number_of_resets_to_assignment_question_learning_tree',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2022_04_05_190234_update_reset_count_to_submissions',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2022_04_05_215344_update_min_number_of_successful_branches_to_assignments',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2022_04_05_215642_update_min_number_of_succesful_branches_to_assignment_question_learning_tree',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2022_04_05_221521_create_learning_tree_successful_branches_table',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2022_04_09_165209_update_applied_to_reset_to_learning_tree_successful_branches',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2022_04_10_014813_update_number_of_resets_as_number_to_assignments',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (301,'2022_04_10_014921_update_number_of_resets_as_number_to_assignment_question_learning_tree',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2022_04_13_132743_create_assignment_templates_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2022_04_20_124447_create_original_submission_scores_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2022_04_20_125443_create_original_assignment_scores_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2022_04_19_223221_upate_show_progress_report_to_courses',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2022_04_22_200517_create_h5p_non_adapts_table',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2022_04_22_201755_update_h5p_non_adapt_id_to_questions',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2022_04_22_220710_create_h5p_type_ids',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2022_04_25_224424_update_assign_to_everyone_to_assignment_templates',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2022_04_26_224526_update_algorithmic_to_assignments',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2022_04_26_225624_update_algorithmic_to_assignment_templates',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2022_04_29_004042_update_libretexts_url_again_to_assignments',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2022_04_29_004145_update_textbook_url_to_courses',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2022_04_29_124259_update_textbook_url_to_assignment_templates',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2022_05_06_150939_create_qti_imports',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2022_05_06_182418_update_json_to_questions',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2022_05_17_134015_create_qti_jobs_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2022_05_19_192204_create_qti_imports_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2022_05_23_125103_udpate_seed_to_seeds',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2022_05_24_192145_update_qti_meta_to_qti_jobs',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2022_05_24_233701_update_assignment_id_to_qti_imports',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2022_06_07_202115_create_skills_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2022_06_07_203802_create_learning_tree_node_skill_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2022_06_13_174956_create_lti_pending_registrations_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2022_06_20_145028_update_url_types_to_data_shops',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2022_06_20_150348_create_textbook_urls_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2022_06_22_153053_update_webwork_code_to_questions',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2022_06_30_171750_update_subject_to_skills',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2022_07_01_142225_drop_skill_to_skills_table',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2022_07_01_143755_update_title_to_skills',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2022_07_01_145321_change_title_to_description',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2022_07_01_152919_update_skill_to_learning_tree_node_skill',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2022_07_01_154345_add_topic_to_learning_objectives',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2022_07_01_155530_update_learning_objective_to_learning_objectives',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (335,'2022_07_03_230359_update_learning_tree_outcome_id_to_learning_tree_node_learning_outcome',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2022_07_04_135352_update_learning_outcome_id_to_questions',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2022_07_07_023752_update_public_to_learning_trees',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2022_07_08_002132_create_empty_learning_tree_nodes_table',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2022_07_09_152821_update_country_to_schools',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2022_07_11_180737_update_a11y_redirect_to_enrollments',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2022_07_12_123855_create_question_learning_outcome_table',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2022_06_17_132914_create_h5p_video_interactions_table',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2022_07_20_144059_create_h5p_max_scores_table',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2022_07_21_230230_update_correct_to_h5p_video_interactions',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2022_07_26_234444_update_source_url_author_license_to_questions',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2022_07_27_225321_update_source_url_to_qti_jobs',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (347,'2022_08_01_070909_create_pending_question_ownership_transfers',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (348,'2022_08_02_182935_update_h5p_owner_imported_to_questions',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (349,'2022_08_05_222715_update_author_to_questions',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (350,'2022_08_04_142552_create_current_question_editors_table',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (351,'2022_07_30_192615_create_adapt_migrations_table',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (352,'2022_08_03_135441_update_non_technology_html_to_questions',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (353,'2022_08_03_142130_create_non_technology_htmls_table',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (354,'2022_08_15_110415_update_assignment_id_to_adapt_migrations',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (355,'2022_08_13_150207_create_learning_tree_migrations_table',120);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (356,'2022_08_15_222647_update_copy_source_id_to_questions',121);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (357,'2022_08_16_004353_update_page_id_library_unique_to_questions',121);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (358,'2022_08_19_132839_create_h5p_adapt_statuses_table',122);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (359,'2022_08_19_202326_create_h5p_type_and_source_urls_table',123);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (360,'2022_08_19_234752_update_h5p_type_to_questions',123);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (361,'2022_08_21_225950_update_question_id_to_h5p_type_and_source_urls',123);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (362,'2022_08_23_205529_remove_h5p_type_id_from_questions',124);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (363,'2022_08_23_210007_drop_h5p_non_adapts',124);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (364,'2022_08_23_222955_drop_h5p_type_and_source_urls_table',124);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (365,'2022_08_31_122912_create_canvas_max_attempts_errors_table',125);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (366,'2022_09_02_223202_create_bad_webworks_table',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (367,'2022_09_08_161426_create_question_nas_table',127);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (368,'2022_09_14_141848_update_time_spent_to_submissions',128);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (369,'2022_09_15_132551_create_assignment_question_time_spents_table',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (370,'2022_09_16_185238_create_tester_access_codes_table',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (371,'2022_09_16_195713_create_tester_courses_table',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (372,'2022_09_20_121147_create_tester_students_table',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (373,'2022_09_22_130810_update_testing_student_to_users',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (374,'2022_09_23_134118_update_assignment_id_to_tester_students',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (375,'2022_09_16_124356_create_submission_histories_table',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (376,'2022_09_16_131104_create_review_histories_table',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (377,'2022_09_30_015535_update_session_id_to_review_histories',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (378,'2022_09_30_120620_update_items_to_data_shops_table',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (379,'2022_09_30_122422_update_times_to_data_shops',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (380,'2022_09_30_122957_update_submission_time_to_data_shops',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (381,'2022_09_30_131839_update_other_items_to_data_shops',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (382,'2022_09_30_134640_update_review_time_saved_to_review_histories',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (383,'2022_09_29_003657_create_case_study_notes_table',133);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (384,'2022_09_29_215238_create_patient_informations_table',133);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (385,'2022_10_03_010644_update_identifier_to_case_study_notes',133);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (386,'2022_10_10_172119_create_time_zones_table',134);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (387,'2022_10_05_222546_create_assignment_question_case_study_notes_table',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (388,'2022_10_06_002335_drop_user_id_to_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (389,'2022_10_07_235302_update_initial_conditions_to_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (390,'2022_10_08_002609_update_updated_information_to_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (391,'2022_10_08_002735_update_updated_items_to_patient_information',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (392,'2022_10_08_004355_update_notes_version_to_assignment_question_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (393,'2022_10_08_012538_update_notes_back_to_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (394,'2022_10_08_220012_drop_version_of_notes_to_assignment_question_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (395,'2022_10_11_001649_update_notes_to_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (396,'2022_10_11_012849_update_nullable_notes_to_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (397,'2022_10_11_113258_update_first_application_to_case_study_notes',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (398,'2022_10_11_115043_update_first_application_of_updated_information_to_patient_informations',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (399,'2022_10_13_002144_update_show_in_updated_information_to_patient_informations',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (400,'2022_10_15_001601_update_weight_units_to_patient_informations',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (401,'2022_10_03_131625_update_table_name_to_assignment_question_time_spents',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (402,'2022_10_03_132202_update_time_spent_as_time_on_task_to_submissions',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (403,'2022_10_03_132928_update_time_spent_to_assignment_question_time_on_tasks',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (404,'2022_10_24_125448_create_adapt_mass_migrations_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (405,'2022_10_25_194624_update_seed_length_to_seeds',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (406,'2022_10_06_123719_create_analytics_dashboards_table',139);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (407,'2022_10_28_132906_update_text_to_case_study_notes',140);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (408,'2022_10_26_131617_create_learning_tree_node_descriptions_table',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (409,'2022_10_26_180614_remove_library_and_rename_page_id_to_learning_tree_histories',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (410,'2022_10_26_184649_update_question_id_to_learning_trees',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (411,'2022_10_26_194716_update_title_and_notes_to_learning_tree_node_descriptions',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (412,'2022_09_26_130443_create_huang_course_questions_table',142);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (413,'2022_11_01_161250_create_qti_json_before_fixing_select_choices_table',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (414,'2022_11_06_142858_update_qti_json_type_to_qti_json_before_fixing_select_choices',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (415,'2022_11_09_134727_create_question_nas_and_dollar_signs_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (416,'2022_11_09_142159_create_webwork_submission_errors_table',145);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (417,'2022_11_10_182405_create_users_with_no_role_table',146);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (418,'2022_11_11_144457_create_zihao_questions_table',147);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (419,'2022_11_11_145015_create_random_leftover_webwork_questions',147);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (420,'2022_11_15_022330_update_copy_source_history_to_questions',148);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (421,'2022_12_07_214545_update_common_question_text_to_assignments',149);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (422,'2022_11_18_140656_create_frameworks_table',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (423,'2022_11_18_140657_create_framework_levels_table',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (424,'2022_11_18_142830_create_learning_objectives_table',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (425,'2022_11_18_142935_create_learning_objective_framework_table',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (426,'2022_11_18_143403_create_learning_objective_question_part_2_table',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (427,'2022_11_18_182950_create_framework_learning_objective_table',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (428,'2022_11_23_183037_create_framework_descriptors_table',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (429,'2022_11_23_183238_create_framework_level_framework_descriptor',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (430,'2022_11_25_175353_create_framework_item_question',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (431,'2022_12_26_161433_create_no_user_logged_in_errors',151);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (432,'2022_12_26_182749_create_canvas_vanity_urls_table',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (433,'2022_12_28_172101_create_old_webwork_paths_table',153);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (434,'2022_12_30_143109_create_webwork_attachments_table',153);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (435,'2023_01_03_231629_create_submission_confirmations_table',154);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (436,'2022_11_18_134343_create_text_submissions_table',155);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (437,'2023_01_07_222136_update_version_to_case_study_notes',156);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (438,'2023_01_12_142945_create_webwork_private_to_natives_table',157);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (439,'2023_01_19_130454_create_not_yet_uploaded_canvas_images_table',158);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (440,'2023_01_26_132940_create_fixed_stylings_table',159);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (441,'2023_01_26_140611_create_fcm_tokens_table',160);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (442,'2023_01_31_184140_create_empty_paragraph_non_technology_html_fixes_table',161);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (443,'2023_02_03_115902_update_qti_json_type_to_questions',162);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (444,'2023_02_07_201243_create_fcm_log_table',163);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (445,'2023_02_14_135315_create_contact_grader_overrides_table',164);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (446,'2023_02_13_142041_create_remove_plain_text_options_table',165);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (447,'2023_02_16_210327_update_items_to_remove_plain_text_options',165);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (448,'2023_02_16_213619_update_formative_students_to_users',166);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (449,'2023_02_16_222433_update_formative_to_courses',166);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (450,'2023_03_03_164153_create_account_customizations_table',167);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (451,'2023_03_03_234917_update_nulls_to_patient_informations',168);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (452,'2023_03_07_133827_create_passback_by_assignments_table',169);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (453,'2023_03_09_132651_update_formative_to_assignments',170);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (454,'2023_03_13_140626_update_formative_to_assignment_templates',170);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (455,'2023_03_16_132104_update_question_url_view_to_assignments',171);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (456,'2023_03_17_122040_create_whitelisted_domains_table',172);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (457,'2023_03_22_152205_update_course_id_index_to_datashops',173);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (458,'2023_03_22_160220_update_school_index_to_data_shops',173);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (459,'2023_03_27_141730_create_unconfirmed_submissions_table',174);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (460,'2023_03_27_171236_update_request_to_unconfirmed_submissions',174);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (461,'2023_04_08_163803_create_rubric_categories_table',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (462,'2023_04_09_021147_create_rubric_category_submissions_table',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (463,'2023_04_09_204027_update_customized_comments_to_rubric_category_submisisons',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (464,'2023_04_09_211346_update_purpose_to_assignments',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (465,'2023_04_12_170938_update_score_customized_feedback_to_rubric_category_submissions',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (466,'2023_04_12_173018_update_customfeeback_to_rubric_category_submissions',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (467,'2023_04_18_123059_update_description_to_courses',176);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (468,'2023_04_18_133322_create_grading_styles_table',176);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (469,'2023_04_18_201219_update_grading_style_id_to_assignments',176);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (470,'2023_04_21_191418_update_custom_title_to_assignment_question',177);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (471,'2023_04_25_105451_update_question_titles_shown_to_assignments',178);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (472,'2023_04_27_185042_update_question_id_to_rubric_categories',179);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (473,'2023_04_27_191026_update_purpose_and_grading_styles_id_to_questions',179);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (474,'2023_04_28_123513_update_assignment_id_to_rubric_category_submissions',179);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (475,'2023_04_28_235609_create_report_toggles_table',179);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (476,'2023_05_02_202940_update_rubric_scale_to_questions',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (477,'2023_05_02_211544_update_percent_to_rubric_categories',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (478,'2023_05_03_124328_update_points_to_report_toggles',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (479,'2023_05_04_113841_drop_rubric_scale_to_questions',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (480,'2023_05_05_130835_create_rubric_category_custom_criteria_table',181);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (481,'2023_05_05_152532_create_rubric_criteria_tests_table',181);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (482,'2023_05_06_115334_create_rubric_category_criteria_pendings_table',181);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (483,'2023_05_10_204733_update_applied_late_penalty_to_submission_files',182);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (484,'2023_05_12_204311_create_question_revisions_table',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (485,'2023_05_16_204455_update_question_revision_id_to_assignment_question',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (486,'2023_05_17_143702_update_type_and_reason_for_edit_to_question_revisions',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (487,'2023_05_17_165026_create_pending_question_revisions_table',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (488,'2023_05_19_132753_update_status_to_pending_question_revisions',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (489,'2023_06_09_232719_update_question_revision_id_to_webwork_attachments',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (490,'2023_06_14_185406_drop_assignment_status_to_pending_question_revisions',184);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (491,'2023_06_15_151128_create_student_emails_with_submissions_table',184);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (492,'2023_06_20_134834_update_question_revision_id_to_rubric_categories',185);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (493,'2023_06_23_225413_update_auto_update_question_revisions_to_courses',186);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (494,'2023_07_03_185254_update_width_height_to_webwork_attachments',187);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (495,'2023_07_03_191703_create_webwork_image_sizes_table',187);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (496,'2023_07_11_144553_create_throttles_table',188);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (497,'2023_07_07_161347_create_learning_tree_node_submissions_table',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (498,'2023_07_12_124033_update_submission_count_proportion_correct_to_learning_tree_node_submissions',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (499,'2023_07_14_020012_drop_learning_tree_columns_to_assignments',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (500,'2023_07_14_020804_update_min_time_to_assignments',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (501,'2023_07_14_103911_update_number_of_successful_branches_for_a_reset_to_assignment_question_learning_tree',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (502,'2023_07_14_222912_update_learning_tree_branch_submission_information_to_submissions',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (503,'2023_07_17_233033_create_learning_tree_resets_table',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (504,'2023_07_20_174527_update_check_for_reset_to_learning_tree_node_submissions',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (505,'2023_07_20_192710_update_show_submission_message_to_learning_node_submissions',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (506,'2023_07_24_134508_create_learning_tree_node_seeds',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (507,'2023_07_25_024605_update_number_of_successful_branches_for_a_reset_to_assignments',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (508,'2023_07_25_025743_rename_number_of_successful_branches_for_a_reset_to_assignment_question_learning_tree',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (509,'2023_07_25_031143_update_learning_tree_columns_to_assignment_templates',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (510,'2023_07_27_144940_update_min_number_of_minutes_in_exposition_node_to_assignments',190);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (511,'2023_07_31_080958_update_description_to_learning_tree_node_descriptions',191);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (512,'2023_08_09_084820_update_description_and_notes_to_learning_trees',192);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (513,'2023_08_12_091957_update_reset_node_after_correct_attempt_to_assignments',193);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (514,'2023_08_14_125315_update_reset_node_after_incorrect_attempt_to_assignment_templates',193);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (515,'2023_08_15_103738_create_submission_score_overrides_table',194);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (516,'2023_08_30_130254_update_user_id_to_contact_grader_overrides',195);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (517,'2023_08_30_152640_create_libre_one_access_codes_table',196);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (518,'2023_09_13_191126_update_grade_passback_to_assignments',197);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (519,'2023_09_21_220631_create_execution_times_table',198);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (520,'2023_09_21_210825_update_original_instructor_id_to_users',199);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (521,'2023_09_26_132159_create_null_lms_grade_passbacks_table',200);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (522,'2023_08_31_193401_create_lms_access_tokens_table',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (523,'2023_09_05_191146_update_lms_course_id_to_courses',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (524,'2023_09_05_194817_update_api_key_to_lti_registrations',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (525,'2023_09_11_155913_update_lms_assignment_id_to_assignments',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (526,'2023_10_02_125321_update_school_id_to_lms_access_tokens',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (527,'2023_10_03_130256_update_api_secret_to_lti_registrations',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (528,'2023_10_05_110131_update_lms_assignment_group_id_to_assignments',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (529,'2023_10_09_160004_create_login_as_users_table',202);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (530,'2023_10_16_134357_update_xml_to_qti_imports',203);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (531,'2023_10_17_131522_create_jobs_table',204);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (532,'2023_10_19_120509_delete_login_as_users_table',205);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (533,'2023_10_30_194939_update_a11y_auto_graded_question_id_to_questions',206);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (534,'2023_10_31_120913_update_a11y_auto_graded_question_id_to_question_revisions',206);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (535,'2023_10_31_123013_drop_old_a11y_technology_from_questions',206);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (536,'2023_11_14_131818_update_question_revision_id_index_to_assignment_question',207);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (537,'2023_11_15_140023_empty_extensions_table',208);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (538,'2023_11_15_143832_update_message_to_qti_jobs',208);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (539,'2023_11_16_133557_create_learning_tree_analytics_table',209);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (540,'2023_11_20_142220_update_course_assignment_info_to_learning_tree_analytics',210);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (541,'2023_11_17_170945_update_assignment_id_index_to_lti_grade_passbacks',211);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (542,'2024_01_17_140614_create_canvas_updates_table',212);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (543,'2024_03_07_140804_update_review_session_id_index_to_review_histories',213);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (544,'2024_03_08_133823_update_launch_id_index_to_lti_launches',214);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (545,'2024_03_10_175424_update_created_at_to_data_shops',215);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (546,'2024_03_11_194926_drop_column_saved_data_shops_to_review_histories',216);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (547,'2024_02_29_010525_create_auto_releases_table',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (548,'2024_03_04_140830_update_auto_release_to_courses',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (549,'2024_03_15_174732_update_show_after_to_auto_releases',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (550,'2024_03_15_185904_update_show_after_to_courses',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (551,'2024_03_18_130453_reorder_courses_table',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (552,'2024_03_28_182254_update_public_and_private_description_to_assignments',218);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (553,'2024_04_01_122402_update_private_description_to_courses',219);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (554,'2024_04_30_135226_update_description_to_questions',220);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (555,'2024_04_30_135500_update_description_to_question_revisions',220);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (556,'2024_05_03_130542_update_technology_id_index_to_questions',221);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (557,'2024_05_03_214926_create_h5p_id_adapt_question_id_table',222);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (558,'2024_05_29_001917_create_data_shops_enrollments',223);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (559,'2024_05_29_123627_update_assignment_id_question_id_to_data_shop_enrollments',223);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (560,'2024_06_03_173537_create_question_media_uploads_table',224);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (561,'2024_06_21_123844_create_question_formatted_types_table',225);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (562,'2024_06_26_011602_create_formatted_question_type_technology_table',225);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (563,'2024_07_01_123553_create_disciplines_table',226);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (564,'2024_07_18_165944_create_discussions_table',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (565,'2024_07_18_195612_create_discussion_comments_table',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (566,'2024_07_29_055002_update_discuss_it_settings_to_assignment_question',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (567,'2024_08_06_144350_update_satisfies_requirement_to_discussion_comments',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (568,'2024_08_12_113611_update_order_to_question_media_uploads',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (569,'2024_08_13_102756_update_vtt_information_to_discussion_comments',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (570,'2024_08_20_123544_update_type_comment_in_submission_files_table',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (571,'2024_08_20_134758_update_foreign_key_to_discussions',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (572,'2024_08_23_115046_update_text_to_question_media_uploads',228);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (573,'2024_08_28_124220_update_lms_user_id_to_users',229);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (574,'2024_04_17_145830_update_auto_release_activated_to_auto_releases',230);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (575,'2024_09_20_171100_update_re_processed_to_discussion_comments',231);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (576,'2024_09_25_214500_create_course_invitations_table',232);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (577,'2024_09_27_122649_create_pending_course_invitations_table',232);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (578,'2024_10_02_160412_create_oidc_credentials_table',233);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (579,'2024_10_02_200512_update_oidc_to_users',233);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (580,'2024_10_09_120910_create_email_central_identity_id_table',234);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (581,'2024_10_16_164203_create_link_to_account_validation_codes_table',235);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (582,'2024_10_16_164329_create_linked_accounts_table',235);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (583,'2024_10_22_165532_create_key_secrets_table',236);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (584,'2024_10_23_124526_create_pending_transcriptions_table',236);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (585,'2024_10_30_123840_update_assign_to_timing_id_index_to_users',237);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (586,'2024_10_30_195150_update_technology_technology_id_to_questions',238);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (587,'2024_10_31_125206_create_log_commands_table',239);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (588,'2024_11_01_180441_update_instructor_user_id_index_to_users',240);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (589,'2024_11_01_180616_update_lms_resource_link_id_index_to_assignments',240);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (590,'2024_11_04_130421_create_active_caches_table',241);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (591,'2024_11_05_173305_create_pending_deleted_users_table',242);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (592,'2024_11_05_200511_update_question_revision_id_to_question_media_uploads',243);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (593,'2024_11_07_122729_create_old_discussion_media_uploads_table',243);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (594,'2024_11_08_145017_update_qti_json_type_index_to_questions',244);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (595,'2024_11_12_141140_create_empty_paragraph_question_media_upload_fixes_table',245);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (596,'2024_12_01_211959_update_order_with_med_int_to_courses',246);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (597,'2024_12_27_141241_create_admin_emails_table',247);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (598,'2024_12_30_164149_update_can_submit_work_to_assignments',248);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (599,'2024_12_30_164532_update_can_submit_work_override_to_assignment_question',248);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (600,'2024_12_31_133432_update_submitted_work_to_submissions',248);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (601,'2025_01_02_163229_update_can_submit_work_to_assignment_templates',248);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (602,'2025_01_06_210005_create_discussion_groups_table',249);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (603,'2025_01_06_210256_update_group_to_discussions',249);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (604,'2025_01_07_191347_update_final_deletion_date_to_users',249);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (605,'2025_01_21_204021_update_central_identity_id_index_to_users',250);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (606,'2025_01_21_160212_update_contact_instructor_auto_grade_to_assignments',251);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (607,'2025_01_22_151946_update_contact_instructor_auto_grade_to_assignment_templates',251);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (608,'2025_01_22_133249_create_logged_in_user_tokens_table',252);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (609,'2025_01_24_185933_update_logged_out_to_logged_in_user_tokens',252);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (610,'2025_01_27_163103_update_sid_to_logged_in_user_tokens',252);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (611,'2025_01_28_132156_update_sid_index_to_logged_in_user_tokens',253);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (612,'2025_01_30_132508_drop_foreign_key_email_logged_in_users',254);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (613,'2025_01_30_150202_update_lms_course_name_to_courses',255);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (614,'2025_01_30_150328_update_lms_assignment_name_to_assignments',255);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (615,'2025_02_07_140157_rename_h5p_video_interactions',256);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (616,'2025_02_13_131852_create_release_assignment_contacted_instructors_table',257);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (617,'2025_03_06_145636_create_cron_job_times_table',258);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (618,'2025_03_21_154635_update_assignment_id_to_lti_launches',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (619,'2025_03_21_170515_create_lti_names_and_roles_table',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (620,'2025_03_21_213806_update_lms_entry_only_to_courses',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (621,'2025_03_25_123722_create_lti_names_and_roles_urls_table',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (622,'2025_03_26_025242_update_adapt_enrollment_notification_date_to_courses',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (623,'2025_03_26_173218_create_lti_assignments_and_grades_urls',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (624,'2025_03_28_215700_update_auto_provisioned_message_to_lti_names_and_roles',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (625,'2025_04_01_125021_update_rubric_to_assignment_question',260);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (626,'2025_04_01_125757_create_rubric_templates_table',260);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (627,'2025_04_01_154851_update_rubric_to_questions',260);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (628,'2025_04_05_011849_update_rubric_to_question_revisions',260);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (629,'2025_04_07_153752_create_rubric_points_breakdowns',260);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (630,'2025_04_24_134140_update_use_existing_rubric_to_assignment_question',261);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (631,'2024_12_09_190637_create_opl_table',262);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (632,'2025_05_05_174747_update_webwork_code_to_opls',262);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (633,'2025_05_05_190938_update_status_to_opls',262);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (634,'2025_05_19_175934_create_webwork_long_radio_buttons_table',263);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (635,'2025_06_09_144841_update_clicker_time_to_submit_to_assignment_question',264);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (636,'2025_06_27_144007_update_release_solution_when_question_is_closed_to_assignment_question',265);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (637,'2025_07_07_143532_update_order_to_assignments',266);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (638,'2025_07_08_150323_update_answer_shown_to_assignment_question',267);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (639,'2025_07_16_153841_create_structures_table',268);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (640,'2025_07_23_181246_create_co_instructors_table',269);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (641,'2025_07_25_084133_create_course_orders_table',269);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (642,'2025_08_12_150310_rename_oidc_credentials_table_to_libreone_credentials_table',270);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (643,'2025_08_04_080923_update_mp4_status_and_message_to_discussion_comments',271);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (644,'2025_08_04_082031_create_pending_mp4_conversions_table',271);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (645,'2025_08_05_064320_update_recording_type_to_discussion_comments',271);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (646,'2025_08_18_153207_update_show_captions_to_question_media_uploads',272);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (647,'2025_09_02_134536_create_maximum_number_of_allowed_attempts_notifications_table',273);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (648,'2025_09_10_134840_update_manual_override_show_open_ended_question_to_assignment_question',274);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (649,'2025_09_16_115716_update_notes_to_questions',275);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (650,'2025_09_16_115749_update_notes_to_question_revisions',275);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (651,'2025_09_15_151522_update_open_ended_submission_type_to_questions',276);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (652,'2025_09_15_151911_update_open_ended_submission_type_to_question_revisions',276);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (653,'2025_09_24_233504_drop_manual_override_show_open_ended_question_to_assignment_question',276);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (654,'2025_10_07_132453_create_s3_log_table',277);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (655,'2025_10_08_132542_create_question_subject_chapter_section_table',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (656,'2025_10_08_150713_create_question_subjects_table',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (657,'2025_10_08_150850_create_question_chapters_table',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (658,'2025_10_08_151531_create_question_sections_table',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (659,'2025_10_08_151808_update_question_subject_chapter_section_id_to_questions',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (660,'2025_10_08_151833_update_question_subject_chapter_section_id_to_question_revisions',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (661,'2025_10_13_154531_update_question_subject_chapter_section_index_to_questions',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (662,'2025_10_14_195726_create_webwork_regex_chapter_mappings_table',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (663,'2025_10_15_114921_create_webwork_regex_section_mappings',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (664,'2025_10_15_124421_create_webwork_regex_subject_mappings',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (665,'2025_10_15_130409_update_subject_id_to_webwork_regex_subject_mappings',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (666,'2025_10_15_133921_update_chapter_id_to_webwork_regex_chapter_mappings',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (667,'2025_10_15_171235_update_section_id_to_webwork_regex_section_mappings',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (668,'2025_10_30_142849_update_pasted_comment_to_discussion_comments',279);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (669,'2025_10_30_183017_update_pasted_text_to_submission_files',279);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (670,'2025_11_11_023913_drop_libretexts_link_to_questions',280);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (671,'2025_11_19_120518_update_attachments_to_questions',281);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (672,'2025_11_19_121112_update_attachments_to_question_revisions',281);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (673,'2025_11_20_190816_create_submitted_works_table',282);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (674,'2025_11_24_184017_create_submissions_recomputed_scores',283);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (675,'2025_12_01_154310_create_assignments_recomputed_scores_table',283);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (676,'2025_12_01_222823_update_submitted_work_format_to_assignments',284);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (677,'2025_12_02_134439_update_format_to_submitted_works',284);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (678,'2025_12_03_134057_update_submitted_work_policy_to_assignments',284);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (679,'2025_12_03_145402_create_submitted_work_pending_scores_table',284);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (680,'2025_12_04_220816_update_submit_work_to_assignment_templates',284);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (681,'2025_01_16_211420_create_forge_assignment_question',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (682,'2025_01_17_193658_create_forge_enrollments_table',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (683,'2025_08_27_123330_update_drafts_to_assignments',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (684,'2025_09_03_150704_update_forge_source_id_to_questions',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (685,'2025_09_03_215712_update_forge_iteration_index_to_assignment_question',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (686,'2026_01_06_175530_update_forge_source_id_to_question_revisions',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (687,'2026_01_06_182127_update_drafts_to_assignment_templates',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (688,'2026_01_07_200835_remove_drafts_from_assignments',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (689,'2026_01_07_201440_add_forge_settings_to_assignment_question',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (690,'2026_01_20_182728_create_assignment_question_forge_draft_table',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (691,'2026_01_20_205440_add_forge_draft_id_index_to_assignment_question_forge_draft',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (692,'2026_02_03_163752_create_forge_tokens_table',286);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (693,'2026_03_02_222109_create_cloudflare_stream_credentials_table',287);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (694,'2026_03_10_141719_create_fix_forge_scores_table',288);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (695,'2026_03_04_182117_update_flashcard_settings_to_assignments',289);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (696,'2026_03_05_202415_add_flashcard_settings_to_assignment_question',289);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (697,'2026_03_11_145640_create_flashcard_tts_logs_table',289);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (698,'2026_03_15_234759_update_flashcard_settings_to_assignment_templates',289);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (699,'2026_03_17_172437_create_launch_redirects_table',290);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (700,'2026_03_17_181351_create_flashcard_audio_vtt_logs_table',291);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (701,'2026_03_17_183359_create_flashcard_ai_audio_logs_table',291);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (702,'2026_02_25_000001_create_cloudflare_stream_videos_table',292);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (703,'2026_03_24_124924_update_error_message_to_cloudflare_stream_videos',292);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (704,'2026_04_06_174926_create_co_question_editors_table',293);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (706,'2026_04_16_192112_create_webwork_macros_table',294);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (709,'2026_04_20_000001_add_scope_to_webwork_macros',295);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (710,'2026_04_20_000002_create_question_webwork_macros_table',295);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (711,'2026_04_20_000003_create_webwork_macro_editors_table',296);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (712,'2026_04_20_000004_create_webwork_macro_revisions_table',296);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (713,'2026_04_20_000005_drop_scope_from_webwork_macros',296);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (714,'2026_04_20_000006_add_is_retired_to_webwork_macros',297);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (716,'2026_04_28_000001_change_macro_to_mediumtext_in_webwork_macros_table',299);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (717,'2026_04_28_000002_change_macro_to_mediumtext_in_webwork_macro_revisions_table',299);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (718,'2026_05_11_172150_create_numerical_migration_backups_table',300);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (719,'2026_05_17_000007_add_source_to_webwork_macros',298);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (721,'2026_05_18_144340_change_official_source_to_standard',301);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (724,'2026_05_21_133108_update_hash_to_macro_and_macro_revisions_table',302);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (725,'2026_05_21_202536_drop_hash_from_macro_and_macro_revisions',303);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (726,'2026_05_26_153601_update_external_source_points_to_assignment_properties',304);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (727,'2026_06_10_155408_create_webwork_macro_co_editors_table',305);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (729,'2026_06_15_193938_create_expired_problem_jwt_logs_table',306);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (730,'2026_07_14_000100_add_current_history_id_to_learning_trees_table',307);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (731,'2026_07_27_000001_create_learning_tree_tag_table',307);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (732,'2026_07_27_000002_create_framework_item_learning_tree_table',307);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (733,'2026_07_27_000003_add_subject_chapter_section_to_learning_trees_table',307);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (735,'2026_08_05_142749_add_learning_tree_to_assignment_question_learning_tree',308);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (738,'2026_08_10_120000_create_lms_outage_courses_table',309);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (739,'2026_08_13_110044_create_question_revision_propagations_table',310);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (741,'2026_09_14_000000_add_dimensions_to_question_media_uploads_table',311);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (742,'2026_09_15_000001_add_time_limit_to_assign_to_timings_table',312);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (743,'2026_09_15_000002_create_assign_to_timing_starts_table',312);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (744,'2026_09_17_000001_remove_cascade_from_assign_to_timing_starts_table',313);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (745,'2026_09_23_000001_add_instructor_adjusted_to_assign_to_timing_starts_table',314);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (747,'2026_09_23_000001_create_discuss_it_chains_table',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (748,'2026_09_23_000002_add_discuss_it_chain_id_to_assignment_question',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (749,'2026_09_23_000003_add_discuss_it_chain_id_index_to_assignment_question',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (750,'2026_09_23_000004_add_posted_in_assignment_id_to_discussion_comments',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (751,'2026_09_23_000005_backfill_posted_in_assignment_id_on_discussion_comments',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (752,'2026_09_23_000006_add_question_user_index_to_discussion_groups',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (753,'2026_10_01_000000_create_show_scores_released_assignments_table',316);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (754,'2026_10_05_000000_add_end_date_index_to_courses_table',316);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (755,'2026_10_06_000000_convert_file_upload_modes_to_both',317);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (756,'2026_10_07_000000_add_number_of_pages_to_submission_files_table',317);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (757,'2026_10_14_000000_create_discussion_comment_timestamps_table',317);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (759,'2026_10_15_000000_create_discussion_comment_attachments_table',318);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (762,'2026_10_19_000000_move_show_points_per_question_to_backup_table',319);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (763,'2026_10_19_000001_add_end_page_to_submission_files',320);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (764,'2026_10_19_000002_create_submission_file_page_tables',320);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (765,'2026_10_19_000003_create_submission_page_annotations_table',320);
