<?php
/**
 * Babel Arcaea Code - WordPress abilities for MCP adapter integration.
 *
 * @package Babel_Arcaea_Code
 * @since   1.6.62
 */

namespace BabelArcaeaCode;

defined('ABSPATH') || exit;

class Abilities {

    /**
     * Register ability bootstrap hook.
     */
    public function __construct() {
        \add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
    }

    /**
     * Register all BAC abilities when the Abilities API is available.
     */
    public function registerAbilities(): void {
        if (!\function_exists('wp_register_ability')) {
            return;
        }

        $this->registerContentTypeAbilities();
        $this->registerContentAbilities('post', 'posts');
        $this->registerContentAbilities('page', 'pages');
        $this->registerContentLookupAbilities();
        $this->registerTaxonomyAbilities();
        $this->registerMediaAbilities();
        $this->registerUserAbilities();
        $this->registerCommentAbilities();
        $this->registerPluginAbilities();
    }

    /**
     * Register BAC content type discovery abilities.
     */
    private function registerContentTypeAbilities(): void {
        $this->registerAbility(
            'bac/content-types-list',
            [
                'label' => 'List content types',
                'description' => 'List manageable WordPress content types exposed by the site.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'additionalProperties' => false,
                ],
                'output_schema' => $this->listOutputSchema(),
                'permission_callback' => fn() => \current_user_can('edit_posts') || \current_user_can('edit_pages'),
                'execute_callback' => fn(array $input = []) => $this->listContentTypes(),
            ]
        );
    }

    /**
     * Register BAC post/page CRUD abilities.
     *
     * @param string $post_type Post type name.
     * @param string $group     Ability group segment.
     */
    private function registerContentAbilities(string $post_type, string $group): void {
        $edit_capability = $this->contentCapability($post_type);

        $this->registerAbility(
            "bac/{$group}-list",
            [
                'label' => 'List ' . $group,
                'description' => 'List WordPress ' . $group . ' with pagination and optional filters.',
                'input_schema' => $this->contentListInputSchema(),
                'output_schema' => $this->listOutputSchema(),
                'permission_callback' => fn() => \current_user_can($edit_capability),
                'execute_callback' => fn(array $input) => $this->listContent($post_type, $input),
            ]
        );

        $this->registerAbility(
            "bac/{$group}-get",
            [
                'label' => 'Get ' . $post_type,
                'description' => 'Get a specific WordPress ' . $post_type . ' by ID.',
                'input_schema' => $this->idInputSchema(),
                'output_schema' => $this->entityOutputSchema(),
                'permission_callback' => fn() => \current_user_can($edit_capability),
                'execute_callback' => fn(array $input) => $this->getContent($post_type, $input),
            ]
        );

        $this->registerAbility(
            "bac/{$group}-create",
            [
                'label' => 'Create ' . $post_type,
                'description' => 'Create a new WordPress ' . $post_type . ' using raw HTML or Gutenberg block content.',
                'input_schema' => $this->contentCreateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => fn() => \current_user_can($edit_capability),
                'execute_callback' => fn(array $input) => $this->createContent($post_type, $input),
            ]
        );

        $this->registerAbility(
            "bac/{$group}-update",
            [
                'label' => 'Update ' . $post_type,
                'description' => 'Update an existing WordPress ' . $post_type . ' without altering its raw content format.',
                'input_schema' => $this->contentUpdateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => fn() => \current_user_can($edit_capability),
                'execute_callback' => fn(array $input) => $this->updateContent($post_type, $input),
            ]
        );

        $this->registerAbility(
            "bac/{$group}-delete",
            [
                'label' => 'Delete ' . $post_type,
                'description' => 'Delete a WordPress ' . $post_type . ' by ID.',
                'input_schema' => $this->deleteInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => fn() => \current_user_can($edit_capability),
                'execute_callback' => fn(array $input) => $this->deleteContent($post_type, $input),
            ]
        );
    }

    /**
     * Register content lookup abilities.
     */
    private function registerContentLookupAbilities(): void {
        $this->registerAbility(
            'bac/content-by-slug',
            [
                'label' => 'Find content by slug',
                'description' => 'Find a post or page by slug, optionally restricted to a post type.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => [
                            'type' => 'string',
                            'minLength' => 1,
                        ],
                        'post_type' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['slug'],
                    'additionalProperties' => false,
                ],
                'output_schema' => $this->entityOutputSchema(),
                'permission_callback' => fn() => \current_user_can('edit_posts') || \current_user_can('edit_pages'),
                'execute_callback' => fn(array $input) => $this->getContentBySlug($input),
            ]
        );
    }

    /**
     * Register taxonomy and term abilities.
     */
    private function registerTaxonomyAbilities(): void {
        $manage_terms = fn() => \current_user_can('manage_categories');

        $this->registerAbility(
            'bac/taxonomies-list',
            [
                'label' => 'List taxonomies',
                'description' => 'List WordPress taxonomies that can be managed through BAC abilities.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'additionalProperties' => false,
                ],
                'output_schema' => $this->listOutputSchema(),
                'permission_callback' => $manage_terms,
                'execute_callback' => fn(array $input = []) => $this->listTaxonomies(),
            ]
        );

        $this->registerAbility(
            'bac/terms-list',
            [
                'label' => 'List terms',
                'description' => 'List terms within a taxonomy.',
                'input_schema' => $this->termListInputSchema(),
                'output_schema' => $this->listOutputSchema(),
                'permission_callback' => $manage_terms,
                'execute_callback' => fn(array $input) => $this->listTerms($input),
            ]
        );

        $this->registerAbility(
            'bac/terms-get',
            [
                'label' => 'Get term',
                'description' => 'Get a specific term by taxonomy and term ID.',
                'input_schema' => $this->termIdInputSchema(),
                'output_schema' => $this->entityOutputSchema(),
                'permission_callback' => $manage_terms,
                'execute_callback' => fn(array $input) => $this->getTermEntity($input),
            ]
        );

        $this->registerAbility(
            'bac/terms-create',
            [
                'label' => 'Create term',
                'description' => 'Create a new term in an existing taxonomy.',
                'input_schema' => $this->termCreateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $manage_terms,
                'execute_callback' => fn(array $input) => $this->createTerm($input),
            ]
        );

        $this->registerAbility(
            'bac/terms-update',
            [
                'label' => 'Update term',
                'description' => 'Update an existing term by taxonomy and term ID.',
                'input_schema' => $this->termUpdateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $manage_terms,
                'execute_callback' => fn(array $input) => $this->updateTerm($input),
            ]
        );

        $this->registerAbility(
            'bac/terms-delete',
            [
                'label' => 'Delete term',
                'description' => 'Delete an existing term from a taxonomy.',
                'input_schema' => $this->termIdInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $manage_terms,
                'execute_callback' => fn(array $input) => $this->deleteTerm($input),
            ]
        );

        $this->registerAbility(
            'bac/content-terms-get',
            [
                'label' => 'Get content terms',
                'description' => 'Get terms assigned to a post or page.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'post_type' => [
                            'type' => 'string',
                            'enum' => ['post', 'page'],
                        ],
                        'post_id' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                        'taxonomy' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['post_type', 'post_id'],
                    'additionalProperties' => false,
                ],
                'output_schema' => $this->entityOutputSchema(),
                'permission_callback' => fn() => \current_user_can('edit_posts') || \current_user_can('edit_pages'),
                'execute_callback' => fn(array $input) => $this->getContentTerms($input),
            ]
        );

        $this->registerAbility(
            'bac/content-terms-assign',
            [
                'label' => 'Assign content terms',
                'description' => 'Assign terms to a post or page within a compatible taxonomy.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'post_type' => [
                            'type' => 'string',
                            'enum' => ['post', 'page'],
                        ],
                        'post_id' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                        'taxonomy' => [
                            'type' => 'string',
                            'minLength' => 1,
                        ],
                        'term_ids' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                                'minimum' => 1,
                            ],
                        ],
                    ],
                    'required' => ['post_type', 'post_id', 'taxonomy', 'term_ids'],
                    'additionalProperties' => false,
                ],
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => fn() => \current_user_can('edit_posts') || \current_user_can('edit_pages'),
                'execute_callback' => fn(array $input) => $this->assignContentTerms($input),
            ]
        );
    }

    /**
     * Register media abilities.
     */
    private function registerMediaAbilities(): void {
        $can_manage_media = fn() => \current_user_can('upload_files');

        $this->registerAbility(
            'bac/media-list',
            [
                'label' => 'List media',
                'description' => 'List media items in the WordPress media library.',
                'input_schema' => $this->mediaListInputSchema(),
                'output_schema' => $this->listOutputSchema(),
                'permission_callback' => $can_manage_media,
                'execute_callback' => fn(array $input) => $this->listMedia($input),
            ]
        );

        $this->registerAbility(
            'bac/media-get',
            [
                'label' => 'Get media',
                'description' => 'Get a specific media item by attachment ID.',
                'input_schema' => $this->idInputSchema(),
                'output_schema' => $this->entityOutputSchema(),
                'permission_callback' => $can_manage_media,
                'execute_callback' => fn(array $input) => $this->getMedia($input),
            ]
        );

        $this->registerAbility(
            'bac/media-create',
            [
                'label' => 'Create media',
                'description' => 'Create a media item by sideloading a remote source URL.',
                'input_schema' => $this->mediaCreateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $can_manage_media,
                'execute_callback' => fn(array $input) => $this->createMedia($input),
            ]
        );

        $this->registerAbility(
            'bac/media-update',
            [
                'label' => 'Update media',
                'description' => 'Update media attachment fields such as title, alt text, caption, and description.',
                'input_schema' => $this->mediaUpdateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $can_manage_media,
                'execute_callback' => fn(array $input) => $this->updateMedia($input),
            ]
        );

        $this->registerAbility(
            'bac/media-delete',
            [
                'label' => 'Delete media',
                'description' => 'Delete a media item by attachment ID.',
                'input_schema' => $this->deleteInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $can_manage_media,
                'execute_callback' => fn(array $input) => $this->deleteMedia($input),
            ]
        );
    }

    /**
     * Register user abilities.
     */
    private function registerUserAbilities(): void {
        $this->registerAbility(
            'bac/users-list',
            [
                'label' => 'List users',
                'description' => 'List WordPress users with pagination and optional role filters.',
                'input_schema' => $this->userListInputSchema(),
                'output_schema' => $this->listOutputSchema(),
                'permission_callback' => fn() => \current_user_can('list_users'),
                'execute_callback' => fn(array $input) => $this->listUsers($input),
            ]
        );

        $this->registerAbility(
            'bac/users-get',
            [
                'label' => 'Get user',
                'description' => 'Get a specific WordPress user by ID.',
                'input_schema' => $this->idInputSchema(),
                'output_schema' => $this->entityOutputSchema(),
                'permission_callback' => fn() => \current_user_can('list_users'),
                'execute_callback' => fn(array $input) => $this->getUserEntity($input),
            ]
        );

        $this->registerAbility(
            'bac/users-create',
            [
                'label' => 'Create user',
                'description' => 'Create a WordPress user with a single role.',
                'input_schema' => $this->userCreateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => fn() => \current_user_can('create_users'),
                'execute_callback' => fn(array $input) => $this->createUser($input),
            ]
        );

        $this->registerAbility(
            'bac/users-update',
            [
                'label' => 'Update user',
                'description' => 'Update an existing WordPress user.',
                'input_schema' => $this->userUpdateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => fn() => \current_user_can('edit_users'),
                'execute_callback' => fn(array $input) => $this->updateUser($input),
            ]
        );

        $this->registerAbility(
            'bac/users-delete',
            [
                'label' => 'Delete user',
                'description' => 'Delete an existing WordPress user, optionally reassigning content.',
                'input_schema' => $this->userDeleteInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => fn() => \current_user_can('delete_users'),
                'execute_callback' => fn(array $input) => $this->deleteUser($input),
            ]
        );
    }

    /**
     * Register comment abilities.
     */
    private function registerCommentAbilities(): void {
        $can_manage_comments = fn() => \current_user_can('moderate_comments') || \current_user_can('edit_posts');

        $this->registerAbility(
            'bac/comments-list',
            [
                'label' => 'List comments',
                'description' => 'List WordPress comments with pagination and optional status filters.',
                'input_schema' => $this->commentListInputSchema(),
                'output_schema' => $this->listOutputSchema(),
                'permission_callback' => $can_manage_comments,
                'execute_callback' => fn(array $input) => $this->listComments($input),
            ]
        );

        $this->registerAbility(
            'bac/comments-get',
            [
                'label' => 'Get comment',
                'description' => 'Get a specific WordPress comment by ID.',
                'input_schema' => $this->idInputSchema(),
                'output_schema' => $this->entityOutputSchema(),
                'permission_callback' => $can_manage_comments,
                'execute_callback' => fn(array $input) => $this->getCommentEntity($input),
            ]
        );

        $this->registerAbility(
            'bac/comments-create',
            [
                'label' => 'Create comment',
                'description' => 'Create a WordPress comment for a specific post or page.',
                'input_schema' => $this->commentCreateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $can_manage_comments,
                'execute_callback' => fn(array $input) => $this->createComment($input),
            ]
        );

        $this->registerAbility(
            'bac/comments-update',
            [
                'label' => 'Update comment',
                'description' => 'Update an existing WordPress comment.',
                'input_schema' => $this->commentUpdateInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $can_manage_comments,
                'execute_callback' => fn(array $input) => $this->updateComment($input),
            ]
        );

        $this->registerAbility(
            'bac/comments-delete',
            [
                'label' => 'Delete comment',
                'description' => 'Delete an existing WordPress comment.',
                'input_schema' => $this->deleteInputSchema(),
                'output_schema' => $this->actionOutputSchema('integer'),
                'permission_callback' => $can_manage_comments,
                'execute_callback' => fn(array $input) => $this->deleteComment($input),
            ]
        );
    }

    /**
     * Register installed plugin abilities.
     */
    private function registerPluginAbilities(): void {
        $can_manage_plugins = fn() => \current_user_can('activate_plugins');

        $this->registerAbility(
            'bac/plugins-list',
            [
                'label' => 'List plugins',
                'description' => 'List installed WordPress plugins and their activation status.',
                'input_schema' => $this->pluginListInputSchema(),
                'output_schema' => $this->listOutputSchema(),
                'permission_callback' => $can_manage_plugins,
                'execute_callback' => fn(array $input) => $this->listPlugins($input),
            ]
        );

        $this->registerAbility(
            'bac/plugins-get',
            [
                'label' => 'Get plugin',
                'description' => 'Get details about an installed WordPress plugin by plugin file slug.',
                'input_schema' => $this->pluginIdInputSchema(),
                'output_schema' => $this->entityOutputSchema(),
                'permission_callback' => $can_manage_plugins,
                'execute_callback' => fn(array $input) => $this->getPluginEntity($input),
            ]
        );

        $this->registerAbility(
            'bac/plugins-activate',
            [
                'label' => 'Activate plugin',
                'description' => 'Activate an installed WordPress plugin by plugin file slug.',
                'input_schema' => $this->pluginIdInputSchema(),
                'output_schema' => $this->actionOutputSchema('string'),
                'permission_callback' => $can_manage_plugins,
                'execute_callback' => fn(array $input) => $this->activatePluginAbility($input),
            ]
        );

        $this->registerAbility(
            'bac/plugins-deactivate',
            [
                'label' => 'Deactivate plugin',
                'description' => 'Deactivate an installed WordPress plugin by plugin file slug.',
                'input_schema' => $this->pluginIdInputSchema(),
                'output_schema' => $this->actionOutputSchema('string'),
                'permission_callback' => $can_manage_plugins,
                'execute_callback' => fn(array $input) => $this->deactivatePluginAbility($input),
            ]
        );
    }

    /**
     * Register a BAC ability with MCP metadata defaults.
     *
     * @param string $name Ability name.
     * @param array  $args Ability registration arguments.
     */
    private function registerAbility(string $name, array $args): void {
        $defaults = [
            'category' => 'site',
            'meta' => [
                'mcp' => [
                    'public' => true,
                ],
                'annotations' => [
                    'idempotent' => false,
                    'destructive' => false,
                    'readonly' => false,
                ],
            ],
        ];

        if (!empty($args['description'])) {
            $lower = \strtolower((string) $args['description']);
            if (\strpos($lower, 'list ') === 0 || \strpos($lower, 'get ') === 0) {
                $defaults['meta']['annotations']['readonly'] = true;
                $defaults['meta']['annotations']['idempotent'] = true;
            }
            if (\strpos($lower, 'delete ') === 0 || \strpos($lower, 'deactivate ') === 0) {
                $defaults['meta']['annotations']['destructive'] = true;
            }
        }

        \wp_register_ability($name, \array_replace_recursive($defaults, $args));
    }

    /**
     * Determine the primary capability for a post type.
     *
     * @param string $post_type Post type name.
     * @return string
     */
    private function contentCapability(string $post_type): string {
        return 'page' === $post_type ? 'edit_pages' : 'edit_posts';
    }

    /**
     * Return a WordPress error when an entity cannot be found.
     *
     * @param string $type Entity type label.
     * @param int    $id   Entity ID.
     * @return \WP_Error
     */
    private function notFound(string $type, int $id): \WP_Error {
        return new \WP_Error(
            'bac_not_found',
            \sprintf('%s with ID %d was not found.', $type, $id),
            ['status' => 404]
        );
    }

    /**
     * Validate a post/page entity for ability use.
     *
     * @param int    $id        Post ID.
     * @param string $post_type Expected post type.
     * @return \WP_Post|\WP_Error
     */
    private function requirePost(int $id, string $post_type) {
        $post = \get_post($id);
        if (!$post instanceof \WP_Post || $post->post_type !== $post_type) {
            return $this->notFound($post_type, $id);
        }
        return $post;
    }

    /**
     * Return a WordPress error when the current user lacks an object capability.
     *
     * @param string $capability Capability name.
     * @return \WP_Error
     */
    private function forbidden(string $capability): \WP_Error {
        return new \WP_Error(
            'bac_forbidden',
            \sprintf('Current user is not allowed to perform capability "%s".', $capability),
            ['status' => 403]
        );
    }

    /**
     * Check a post object capability and return a standard error on failure.
     *
     * @param string $capability Object capability name.
     * @param int    $post_id    Post ID.
     * @return true|\WP_Error
     */
    private function requirePostCapability(string $capability, int $post_id) {
        if (!\current_user_can($capability, $post_id)) {
            return $this->forbidden($capability);
        }
        return true;
    }

    /**
     * Check a taxonomy capability and return a standard error on failure.
     *
     * @param string $taxonomy        Taxonomy name.
     * @param string $capability_name Capability property name.
     * @return true|\WP_Error
     */
    private function requireTaxonomyCapability(string $taxonomy, string $capability_name) {
        $taxonomy_object = \get_taxonomy($taxonomy);
        if (!$taxonomy_object || empty($taxonomy_object->cap->{$capability_name})) {
            return $this->forbidden($capability_name);
        }

        $capability = (string) $taxonomy_object->cap->{$capability_name};
        if (!\current_user_can($capability)) {
            return $this->forbidden($capability);
        }

        return true;
    }

    /**
     * Validate status, author, and parent changes before direct post writes.
     *
     * @param string        $post_type Post type name.
     * @param array         $input     Ability input.
     * @param \WP_Post|null $existing  Existing post for updates.
     * @return true|\WP_Error
     */
    private function requireContentWritePermission(string $post_type, array $input, ?\WP_Post $existing = null) {
        $type_object = \get_post_type_object($post_type);
        if (!$type_object) {
            return new \WP_Error('bac_invalid_post_type', 'Invalid post type.', ['status' => 400]);
        }

        if ($existing instanceof \WP_Post) {
            $can_edit = $this->requirePostCapability('edit_post', (int) $existing->ID);
            if ($can_edit instanceof \WP_Error) {
                return $can_edit;
            }
        } elseif (!\current_user_can($type_object->cap->create_posts)) {
            return $this->forbidden((string) $type_object->cap->create_posts);
        }

        if (!empty($input['status'])) {
            $status = (string) $input['status'];
            if (\in_array($status, ['publish', 'future', 'private'], true)
                && !\current_user_can($type_object->cap->publish_posts)
            ) {
                return $this->forbidden((string) $type_object->cap->publish_posts);
            }
        }

        if (isset($input['author']) && (int) $input['author'] !== \get_current_user_id()) {
            $edit_others = $type_object->cap->edit_others_posts ?? '';
            if ('' === $edit_others || !\current_user_can($edit_others)) {
                return $this->forbidden((string) $edit_others);
            }
        }

        if (isset($input['parent']) && (int) $input['parent'] > 0) {
            $parent = $this->requirePost((int) $input['parent'], $post_type);
            if ($parent instanceof \WP_Error) {
                return $parent;
            }

            $can_edit_parent = $this->requirePostCapability('edit_post', (int) $parent->ID);
            if ($can_edit_parent instanceof \WP_Error) {
                return $can_edit_parent;
            }
        }

        return true;
    }

    /**
     * Update post meta only when the current user may edit each meta key.
     *
     * @param int   $post_id Post ID.
     * @param array $meta    Meta values keyed by meta name.
     * @return true|\WP_Error
     */
    private function updateContentMeta(int $post_id, array $meta) {
        foreach ($meta as $key => $value) {
            $meta_key = (string) $key;
            if (!\current_user_can('edit_post_meta', $post_id, $meta_key)) {
                return $this->forbidden('edit_post_meta');
            }
            \update_post_meta($post_id, $meta_key, $value);
        }

        return true;
    }

    /**
     * Update a featured image only after object and meta permissions pass.
     *
     * @param int $post_id       Post ID.
     * @param int $attachment_id Attachment ID.
     * @return true|\WP_Error
     */
    private function updateFeaturedMedia(int $post_id, int $attachment_id) {
        $attachment = $this->requireAttachment($attachment_id);
        if ($attachment instanceof \WP_Error) {
            return $attachment;
        }

        if (!\current_user_can('edit_post_meta', $post_id, '_thumbnail_id')) {
            return $this->forbidden('edit_post_meta');
        }

        \set_post_thumbnail($post_id, $attachment_id);
        return true;
    }

    /**
     * Validate an attachment entity for ability use.
     *
     * @param int $id Attachment ID.
     * @return \WP_Post|\WP_Error
     */
    private function requireAttachment(int $id) {
        $post = \get_post($id);
        if (!$post instanceof \WP_Post || 'attachment' !== $post->post_type) {
            return $this->notFound('attachment', $id);
        }
        return $post;
    }

    /**
     * Validate a taxonomy name.
     *
     * @param string $taxonomy Taxonomy name.
     * @return string|\WP_Error
     */
    private function requireTaxonomy(string $taxonomy) {
        if (!\taxonomy_exists($taxonomy)) {
            return new \WP_Error(
                'bac_invalid_taxonomy',
                \sprintf('Taxonomy "%s" does not exist.', $taxonomy),
                ['status' => 400]
            );
        }
        return $taxonomy;
    }

    /**
     * Validate a term by taxonomy and ID.
     *
     * @param string $taxonomy Taxonomy name.
     * @param int    $term_id  Term ID.
     * @return \WP_Term|\WP_Error
     */
    private function requireTerm(string $taxonomy, int $term_id) {
        $taxonomy_result = $this->requireTaxonomy($taxonomy);
        if ($taxonomy_result instanceof \WP_Error) {
            return $taxonomy_result;
        }

        $term = \get_term($term_id, $taxonomy);
        if (!$term instanceof \WP_Term) {
            return $this->notFound('term', $term_id);
        }
        return $term;
    }

    /**
     * Validate a comment.
     *
     * @param int $id Comment ID.
     * @return \WP_Comment|\WP_Error
     */
    private function requireComment(int $id) {
        $comment = \get_comment($id);
        if (!$comment instanceof \WP_Comment) {
            return $this->notFound('comment', $id);
        }
        return $comment;
    }

    /**
     * Validate a user.
     *
     * @param int $id User ID.
     * @return \WP_User|\WP_Error
     */
    private function requireUser(int $id) {
        $user = \get_user_by('id', $id);
        if (!$user instanceof \WP_User) {
            return $this->notFound('user', $id);
        }
        return $user;
    }

    /**
     * Validate requested role changes before direct user writes.
     *
     * @param array         $input Ability input.
     * @param \WP_User|null $user  Existing user for updates.
     * @return true|\WP_Error
     */
    private function requireUserRolePermission(array $input, ?\WP_User $user = null) {
        if (empty($input['role'])) {
            return true;
        }

        $role = (string) $input['role'];
        if (!\get_role($role)) {
            return new \WP_Error(
                'bac_invalid_role',
                \sprintf('Role "%s" does not exist.', $role),
                ['status' => 400]
            );
        }

        if ($user instanceof \WP_User) {
            if (!\current_user_can('promote_user', $user->ID)) {
                return $this->forbidden('promote_user');
            }
            return true;
        }

        if (!\current_user_can('promote_users')) {
            return $this->forbidden('promote_users');
        }

        return true;
    }

    /**
     * Validate an installed plugin file slug.
     *
     * @param string $plugin_file Plugin file path.
     * @return string|\WP_Error
     */
    private function requirePluginFile(string $plugin_file) {
        $this->loadPluginFunctions();

        $plugins = \get_plugins();
        if (!isset($plugins[$plugin_file])) {
            return new \WP_Error(
                'bac_invalid_plugin',
                \sprintf('Plugin "%s" is not installed.', $plugin_file),
                ['status' => 400]
            );
        }
        return $plugin_file;
    }

    /**
     * Load plugin management functions when needed.
     */
    private function loadPluginFunctions(): void {
        if (!\function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * Load media handling helpers when needed.
     */
    private function loadMediaFunctions(): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    /**
     * Load user deletion helpers when needed.
     */
    private function loadUserFunctions(): void {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    /**
     * Build a standard action response payload.
     *
     * @param int|string $id      Entity identifier.
     * @param string     $type    Entity type.
     * @param string     $message Human readable message.
     * @param array      $entity  Optional entity summary.
     * @return array<string,mixed>
     */
    private function actionResult($id, string $type, string $message, array $entity = []): array {
        $payload = [
            'id' => $id,
            'type' => $type,
            'status' => 'success',
            'message' => $message,
        ];

        if ([] !== $entity) {
            $payload['entity'] = $entity;
        }

        return $payload;
    }

    /**
     * Build a paginated list payload.
     *
     * @param array $items    Listed items.
     * @param int   $total    Total count.
     * @param int   $page     Current page.
     * @param int   $per_page Page size.
     * @return array<string,mixed>
     */
    private function listResult(array $items, int $total, int $page, int $per_page): array {
        return [
            'items' => \array_values($items),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * Build a generic list output schema.
     *
     * @return array<string,mixed>
     */
    private function listOutputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                    ],
                ],
                'total' => [
                    'type' => 'integer',
                ],
                'page' => [
                    'type' => 'integer',
                ],
                'per_page' => [
                    'type' => 'integer',
                ],
            ],
            'required' => ['items', 'total', 'page', 'per_page'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Build a generic entity output schema.
     *
     * @return array<string,mixed>
     */
    private function entityOutputSchema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => true,
        ];
    }

    /**
     * Build a standard action output schema.
     *
     * @param string $id_type Identifier type.
     * @return array<string,mixed>
     */
    private function actionOutputSchema(string $id_type): array {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => $id_type,
                ],
                'type' => [
                    'type' => 'string',
                ],
                'status' => [
                    'type' => 'string',
                ],
                'message' => [
                    'type' => 'string',
                ],
                'entity' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
            ],
            'required' => ['id', 'type', 'status', 'message'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Common ID schema.
     *
     * @return array<string,mixed>
     */
    private function idInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Common delete schema.
     *
     * @return array<string,mixed>
     */
    private function deleteInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'force' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Content list input schema.
     *
     * @return array<string,mixed>
     */
    private function contentListInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 10,
                ],
                'status' => [
                    'type' => 'string',
                ],
                'search' => [
                    'type' => 'string',
                ],
                'author' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'parent' => [
                    'type' => 'integer',
                    'minimum' => 0,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * Content create schema.
     *
     * @return array<string,mixed>
     */
    private function contentCreateInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Raw HTML or Gutenberg block markup.',
                ],
                'status' => [
                    'type' => 'string',
                    'default' => 'draft',
                ],
                'excerpt' => [
                    'type' => 'string',
                ],
                'slug' => [
                    'type' => 'string',
                ],
                'featured_media' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'author' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'parent' => [
                    'type' => 'integer',
                    'minimum' => 0,
                ],
                'menu_order' => [
                    'type' => 'integer',
                ],
                'meta' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
            ],
            'required' => ['title'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Content update schema.
     *
     * @return array<string,mixed>
     */
    private function contentUpdateInputSchema(): array {
        $schema = $this->contentCreateInputSchema();
        $schema['properties']['id'] = [
            'type' => 'integer',
            'minimum' => 1,
        ];
        $schema['required'] = ['id'];
        return $schema;
    }

    /**
     * Term list schema.
     *
     * @return array<string,mixed>
     */
    private function termListInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'taxonomy' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 50,
                ],
                'search' => [
                    'type' => 'string',
                ],
                'hide_empty' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
            'required' => ['taxonomy'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Term identity schema.
     *
     * @return array<string,mixed>
     */
    private function termIdInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'taxonomy' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'required' => ['taxonomy', 'id'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Term create schema.
     *
     * @return array<string,mixed>
     */
    private function termCreateInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'taxonomy' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'name' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'slug' => [
                    'type' => 'string',
                ],
                'description' => [
                    'type' => 'string',
                ],
                'parent' => [
                    'type' => 'integer',
                    'minimum' => 0,
                ],
            ],
            'required' => ['taxonomy', 'name'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Term update schema.
     *
     * @return array<string,mixed>
     */
    private function termUpdateInputSchema(): array {
        $schema = $this->termCreateInputSchema();
        $schema['properties']['id'] = [
            'type' => 'integer',
            'minimum' => 1,
        ];
        $schema['required'] = ['taxonomy', 'id'];
        return $schema;
    }

    /**
     * Media list schema.
     *
     * @return array<string,mixed>
     */
    private function mediaListInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 20,
                ],
                'search' => [
                    'type' => 'string',
                ],
                'author' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * Media create schema.
     *
     * @return array<string,mixed>
     */
    private function mediaCreateInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'source_url' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'minLength' => 1,
                ],
                'title' => [
                    'type' => 'string',
                ],
                'alt_text' => [
                    'type' => 'string',
                ],
                'caption' => [
                    'type' => 'string',
                ],
                'description' => [
                    'type' => 'string',
                ],
                'parent' => [
                    'type' => 'integer',
                    'minimum' => 0,
                ],
            ],
            'required' => ['source_url'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Media update schema.
     *
     * @return array<string,mixed>
     */
    private function mediaUpdateInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'title' => [
                    'type' => 'string',
                ],
                'alt_text' => [
                    'type' => 'string',
                ],
                'caption' => [
                    'type' => 'string',
                ],
                'description' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }

    /**
     * User list schema.
     *
     * @return array<string,mixed>
     */
    private function userListInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 20,
                ],
                'search' => [
                    'type' => 'string',
                ],
                'role' => [
                    'type' => 'string',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * User create schema.
     *
     * @return array<string,mixed>
     */
    private function userCreateInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'minLength' => 1,
                ],
                'password' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'role' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'display_name' => [
                    'type' => 'string',
                ],
                'first_name' => [
                    'type' => 'string',
                ],
                'last_name' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['username', 'email', 'password'],
            'additionalProperties' => false,
        ];
    }

    /**
     * User update schema.
     *
     * @return array<string,mixed>
     */
    private function userUpdateInputSchema(): array {
        $schema = $this->userCreateInputSchema();
        $schema['properties']['id'] = [
            'type' => 'integer',
            'minimum' => 1,
        ];
        $schema['required'] = ['id'];
        return $schema;
    }

    /**
     * User delete schema.
     *
     * @return array<string,mixed>
     */
    private function userDeleteInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'reassign' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Comment list schema.
     *
     * @return array<string,mixed>
     */
    private function commentListInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 20,
                ],
                'status' => [
                    'type' => 'string',
                ],
                'post_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'search' => [
                    'type' => 'string',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * Comment create schema.
     *
     * @return array<string,mixed>
     */
    private function commentCreateInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'content' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'status' => [
                    'type' => 'string',
                ],
                'author_name' => [
                    'type' => 'string',
                ],
                'author_email' => [
                    'type' => 'string',
                    'format' => 'email',
                ],
                'author_url' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'parent' => [
                    'type' => 'integer',
                    'minimum' => 0,
                ],
            ],
            'required' => ['post_id', 'content'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Comment update schema.
     *
     * @return array<string,mixed>
     */
    private function commentUpdateInputSchema(): array {
        $schema = $this->commentCreateInputSchema();
        $schema['properties']['id'] = [
            'type' => 'integer',
            'minimum' => 1,
        ];
        unset($schema['properties']['post_id']);
        $schema['required'] = ['id'];
        return $schema;
    }

    /**
     * Plugin list schema.
     *
     * @return array<string,mixed>
     */
    private function pluginListInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 50,
                ],
                'search' => [
                    'type' => 'string',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['all', 'active', 'inactive'],
                    'default' => 'all',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * Plugin identity schema.
     *
     * @return array<string,mixed>
     */
    private function pluginIdInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'plugin' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
            ],
            'required' => ['plugin'],
            'additionalProperties' => false,
        ];
    }

    /**
     * List manageable content types.
     *
     * @return array<string,mixed>
     */
    private function listContentTypes(): array {
        $types = \get_post_types(['show_ui' => true], 'objects');
        $items = [];

        foreach ($types as $type) {
            if ('attachment' === $type->name) {
                continue;
            }

            $items[] = [
                'slug' => $type->name,
                'label' => $type->label,
                'description' => $type->description,
                'hierarchical' => (bool) $type->hierarchical,
                'rest_base' => $type->rest_base,
                'supports' => \get_all_post_type_supports($type->name),
            ];
        }

        return $this->listResult($items, \count($items), 1, \count($items));
    }

    /**
     * List posts or pages.
     *
     * @param string $post_type Post type name.
     * @param array  $input     Ability input.
     * @return array<string,mixed>
     */
    private function listContent(string $post_type, array $input): array {
        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $per_page = isset($input['per_page']) ? (int) $input['per_page'] : 10;

        $query = new \WP_Query(
            [
                'post_type' => $post_type,
                'post_status' => $input['status'] ?? ['publish', 'draft', 'pending', 'private', 'future'],
                'posts_per_page' => $per_page,
                'paged' => $page,
                's' => $input['search'] ?? '',
                'author' => isset($input['author']) ? (int) $input['author'] : null,
                'post_parent' => isset($input['parent']) ? (int) $input['parent'] : null,
                'perm' => 'readable',
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => false,
            ]
        );

        $items = \array_map(
            fn(\WP_Post $post) => $this->formatContent($post),
            \array_values(
                \array_filter(
                    $query->posts,
                    static fn(\WP_Post $post): bool => \current_user_can('edit_post', $post->ID)
                )
            )
        );

        return $this->listResult($items, \count($items), $page, $per_page);
    }

    /**
     * Get a single post or page.
     *
     * @param string $post_type Post type name.
     * @param array  $input     Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function getContent(string $post_type, array $input) {
        $post = $this->requirePost((int) $input['id'], $post_type);
        if ($post instanceof \WP_Error) {
            return $post;
        }

        $can_edit = $this->requirePostCapability('edit_post', (int) $post->ID);
        if ($can_edit instanceof \WP_Error) {
            return $can_edit;
        }

        return $this->formatContent($post);
    }

    /**
     * Create a post or page.
     *
     * @param string $post_type Post type name.
     * @param array  $input     Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function createContent(string $post_type, array $input) {
        $can_write = $this->requireContentWritePermission($post_type, $input);
        if ($can_write instanceof \WP_Error) {
            return $can_write;
        }

        $postarr = $this->contentPostarr($post_type, $input);
        $id = \wp_insert_post($postarr, true);
        if ($id instanceof \WP_Error) {
            return $id;
        }

        if (isset($input['meta']) && \is_array($input['meta'])) {
            $meta_result = $this->updateContentMeta((int) $id, $input['meta']);
            if ($meta_result instanceof \WP_Error) {
                return $meta_result;
            }
        }
        if (isset($input['featured_media'])) {
            $featured_result = $this->updateFeaturedMedia((int) $id, (int) $input['featured_media']);
            if ($featured_result instanceof \WP_Error) {
                return $featured_result;
            }
        }

        $post = $this->requirePost((int) $id, $post_type);
        if ($post instanceof \WP_Error) {
            return $post;
        }

        return $this->actionResult(
            (int) $id,
            $post_type,
            \sprintf('%s created successfully.', \ucfirst($post_type)),
            $this->formatContent($post)
        );
    }

    /**
     * Update a post or page.
     *
     * @param string $post_type Post type name.
     * @param array  $input     Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function updateContent(string $post_type, array $input) {
        $post = $this->requirePost((int) $input['id'], $post_type);
        if ($post instanceof \WP_Error) {
            return $post;
        }

        $can_write = $this->requireContentWritePermission($post_type, $input, $post);
        if ($can_write instanceof \WP_Error) {
            return $can_write;
        }

        $postarr = $this->contentPostarr($post_type, $input);
        $postarr['ID'] = $post->ID;

        $id = \wp_update_post($postarr, true);
        if ($id instanceof \WP_Error) {
            return $id;
        }

        if (isset($input['meta']) && \is_array($input['meta'])) {
            $meta_result = $this->updateContentMeta((int) $id, $input['meta']);
            if ($meta_result instanceof \WP_Error) {
                return $meta_result;
            }
        }
        if (isset($input['featured_media'])) {
            $featured_result = $this->updateFeaturedMedia((int) $id, (int) $input['featured_media']);
            if ($featured_result instanceof \WP_Error) {
                return $featured_result;
            }
        }

        $updated = $this->requirePost((int) $id, $post_type);
        if ($updated instanceof \WP_Error) {
            return $updated;
        }

        return $this->actionResult(
            (int) $id,
            $post_type,
            \sprintf('%s updated successfully.', \ucfirst($post_type)),
            $this->formatContent($updated)
        );
    }

    /**
     * Delete a post or page.
     *
     * @param string $post_type Post type name.
     * @param array  $input     Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function deleteContent(string $post_type, array $input) {
        $post = $this->requirePost((int) $input['id'], $post_type);
        if ($post instanceof \WP_Error) {
            return $post;
        }

        $can_delete = $this->requirePostCapability('delete_post', (int) $post->ID);
        if ($can_delete instanceof \WP_Error) {
            return $can_delete;
        }

        $deleted = \wp_delete_post($post->ID, !empty($input['force']));
        if (!$deleted instanceof \WP_Post) {
            return new \WP_Error(
                'bac_delete_failed',
                \sprintf('Failed to delete %s with ID %d.', $post_type, $post->ID),
                ['status' => 500]
            );
        }

        return $this->actionResult(
            $post->ID,
            $post_type,
            \sprintf('%s deleted successfully.', \ucfirst($post_type)),
            $this->formatContent($post)
        );
    }

    /**
     * Build a post array from content input.
     *
     * @param string $post_type Post type name.
     * @param array  $input     Ability input.
     * @return array<string,mixed>
     */
    private function contentPostarr(string $post_type, array $input): array {
        $postarr = [
            'post_type' => $post_type,
        ];

        $map = [
            'title' => 'post_title',
            'content' => 'post_content',
            'status' => 'post_status',
            'excerpt' => 'post_excerpt',
            'slug' => 'post_name',
            'author' => 'post_author',
            'parent' => 'post_parent',
            'menu_order' => 'menu_order',
        ];

        foreach ($map as $input_key => $post_key) {
            if (\array_key_exists($input_key, $input)) {
                $postarr[$post_key] = $input[$input_key];
            }
        }

        if (!isset($postarr['post_status'])) {
            $postarr['post_status'] = 'draft';
        }

        return $postarr;
    }

    /**
     * Format a post/page entity for ability output.
     *
     * @param \WP_Post $post Post object.
     * @return array<string,mixed>
     */
    private function formatContent(\WP_Post $post): array {
        return [
            'id' => (int) $post->ID,
            'type' => $post->post_type,
            'status' => $post->post_status,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'excerpt' => $post->post_excerpt,
            'content' => $post->post_content,
            'author' => (int) $post->post_author,
            'parent' => (int) $post->post_parent,
            'menu_order' => (int) $post->menu_order,
            'featured_media' => (int) \get_post_thumbnail_id($post),
            'date_gmt' => $post->post_date_gmt,
            'modified_gmt' => $post->post_modified_gmt,
            'link' => \get_permalink($post),
        ];
    }

    /**
     * Find content by slug.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function getContentBySlug(array $input) {
        $slug = (string) $input['slug'];
        $post_types = [];

        if (!empty($input['post_type'])) {
            $post_types[] = (string) $input['post_type'];
        } else {
            $post_types = \array_values(
                \array_filter(
                    \get_post_types(['public' => true], 'names'),
                    static fn(string $type): bool => 'attachment' !== $type
                )
            );
        }

        foreach ($post_types as $post_type) {
            $posts = \get_posts(
                [
                    'post_type' => $post_type,
                    'name' => $slug,
                    'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
                    'posts_per_page' => 1,
                    'perm' => 'readable',
                    'suppress_filters' => true,
                ]
            );

            if (!empty($posts[0])
                && $posts[0] instanceof \WP_Post
                && \current_user_can('edit_post', $posts[0]->ID)
            ) {
                return $this->formatContent($posts[0]);
            }
        }

        return new \WP_Error(
            'bac_slug_not_found',
            \sprintf('No content found for slug "%s".', $slug),
            ['status' => 404]
        );
    }

    /**
     * List available taxonomies.
     *
     * @return array<string,mixed>
     */
    private function listTaxonomies(): array {
        $taxonomies = \get_taxonomies(['show_ui' => true], 'objects');
        $items = [];

        foreach ($taxonomies as $taxonomy) {
            $items[] = [
                'slug' => $taxonomy->name,
                'label' => $taxonomy->label,
                'description' => $taxonomy->description,
                'hierarchical' => (bool) $taxonomy->hierarchical,
                'object_type' => \array_values($taxonomy->object_type),
                'rest_base' => $taxonomy->rest_base,
            ];
        }

        return $this->listResult($items, \count($items), 1, \count($items));
    }

    /**
     * List terms for a taxonomy.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function listTerms(array $input) {
        $taxonomy = $this->requireTaxonomy((string) $input['taxonomy']);
        if ($taxonomy instanceof \WP_Error) {
            return $taxonomy;
        }
        $can_manage = $this->requireTaxonomyCapability($taxonomy, 'manage_terms');
        if ($can_manage instanceof \WP_Error) {
            return $can_manage;
        }

        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $per_page = isset($input['per_page']) ? (int) $input['per_page'] : 50;
        $offset = ($page - 1) * $per_page;

        $query_args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => !empty($input['hide_empty']),
            'search' => $input['search'] ?? '',
            'number' => $per_page,
            'offset' => $offset,
        ];

        $terms = \get_terms($query_args);
        if ($terms instanceof \WP_Error) {
            return $terms;
        }

        $count = (int) \wp_count_terms(
            [
                'taxonomy' => $taxonomy,
                'hide_empty' => !empty($input['hide_empty']),
                'search' => $input['search'] ?? '',
            ]
        );

        $items = \array_map(fn(\WP_Term $term) => $this->formatTerm($term), $terms);

        return $this->listResult($items, $count, $page, $per_page);
    }

    /**
     * Get a single term.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function getTermEntity(array $input) {
        $term = $this->requireTerm((string) $input['taxonomy'], (int) $input['id']);
        if ($term instanceof \WP_Error) {
            return $term;
        }
        $can_manage = $this->requireTaxonomyCapability($term->taxonomy, 'manage_terms');
        if ($can_manage instanceof \WP_Error) {
            return $can_manage;
        }

        return $this->formatTerm($term);
    }

    /**
     * Create a term.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function createTerm(array $input) {
        $taxonomy = $this->requireTaxonomy((string) $input['taxonomy']);
        if ($taxonomy instanceof \WP_Error) {
            return $taxonomy;
        }
        $can_edit = $this->requireTaxonomyCapability($taxonomy, 'edit_terms');
        if ($can_edit instanceof \WP_Error) {
            return $can_edit;
        }

        $result = \wp_insert_term(
            (string) $input['name'],
            $taxonomy,
            [
                'slug' => $input['slug'] ?? '',
                'description' => $input['description'] ?? '',
                'parent' => isset($input['parent']) ? (int) $input['parent'] : 0,
            ]
        );
        if ($result instanceof \WP_Error) {
            return $result;
        }

        $term = $this->requireTerm($taxonomy, (int) $result['term_id']);
        if ($term instanceof \WP_Error) {
            return $term;
        }

        return $this->actionResult(
            (int) $term->term_id,
            'term',
            'Term created successfully.',
            $this->formatTerm($term)
        );
    }

    /**
     * Update a term.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function updateTerm(array $input) {
        $term = $this->requireTerm((string) $input['taxonomy'], (int) $input['id']);
        if ($term instanceof \WP_Error) {
            return $term;
        }
        $can_edit = $this->requireTaxonomyCapability($term->taxonomy, 'edit_terms');
        if ($can_edit instanceof \WP_Error) {
            return $can_edit;
        }

        $args = [];
        foreach (['name', 'slug', 'description'] as $field) {
            if (\array_key_exists($field, $input)) {
                $args[$field] = $input[$field];
            }
        }
        if (\array_key_exists('parent', $input)) {
            $args['parent'] = (int) $input['parent'];
        }

        $result = \wp_update_term($term->term_id, $term->taxonomy, $args);
        if ($result instanceof \WP_Error) {
            return $result;
        }

        $updated = $this->requireTerm($term->taxonomy, (int) $term->term_id);
        if ($updated instanceof \WP_Error) {
            return $updated;
        }

        return $this->actionResult(
            (int) $updated->term_id,
            'term',
            'Term updated successfully.',
            $this->formatTerm($updated)
        );
    }

    /**
     * Delete a term.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function deleteTerm(array $input) {
        $term = $this->requireTerm((string) $input['taxonomy'], (int) $input['id']);
        if ($term instanceof \WP_Error) {
            return $term;
        }
        $can_delete = $this->requireTaxonomyCapability($term->taxonomy, 'delete_terms');
        if ($can_delete instanceof \WP_Error) {
            return $can_delete;
        }

        $deleted = \wp_delete_term($term->term_id, $term->taxonomy);
        if (!$deleted) {
            return new \WP_Error(
                'bac_term_delete_failed',
                \sprintf('Failed to delete term ID %d.', $term->term_id),
                ['status' => 500]
            );
        }

        return $this->actionResult(
            (int) $term->term_id,
            'term',
            'Term deleted successfully.',
            $this->formatTerm($term)
        );
    }

    /**
     * Format a term entity.
     *
     * @param \WP_Term $term Term object.
     * @return array<string,mixed>
     */
    private function formatTerm(\WP_Term $term): array {
        return [
            'id' => (int) $term->term_id,
            'type' => 'term',
            'taxonomy' => $term->taxonomy,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'parent' => (int) $term->parent,
            'count' => (int) $term->count,
        ];
    }

    /**
     * Get terms assigned to a content entity.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function getContentTerms(array $input) {
        $post = $this->requirePost((int) $input['post_id'], (string) $input['post_type']);
        if ($post instanceof \WP_Error) {
            return $post;
        }
        $can_edit_post = $this->requirePostCapability('edit_post', (int) $post->ID);
        if ($can_edit_post instanceof \WP_Error) {
            return $can_edit_post;
        }

        $taxonomies = !empty($input['taxonomy'])
            ? [(string) $input['taxonomy']]
            : \get_object_taxonomies($post->post_type);

        $terms_by_taxonomy = [];
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_result = $this->requireTaxonomy($taxonomy);
            if ($taxonomy_result instanceof \WP_Error) {
                return $taxonomy_result;
            }

            $terms = \get_the_terms($post, $taxonomy);
            if ($terms instanceof \WP_Error) {
                return $terms;
            }

            $terms_by_taxonomy[$taxonomy] = empty($terms)
                ? []
                : \array_map(fn(\WP_Term $term) => $this->formatTerm($term), $terms);
        }

        return [
            'id' => (int) $post->ID,
            'type' => 'content-terms',
            'post_type' => $post->post_type,
            'terms' => $terms_by_taxonomy,
        ];
    }

    /**
     * Assign terms to a content entity.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function assignContentTerms(array $input) {
        $post = $this->requirePost((int) $input['post_id'], (string) $input['post_type']);
        if ($post instanceof \WP_Error) {
            return $post;
        }
        $can_edit_post = $this->requirePostCapability('edit_post', (int) $post->ID);
        if ($can_edit_post instanceof \WP_Error) {
            return $can_edit_post;
        }

        $taxonomy = $this->requireTaxonomy((string) $input['taxonomy']);
        if ($taxonomy instanceof \WP_Error) {
            return $taxonomy;
        }
        $can_assign = $this->requireTaxonomyCapability($taxonomy, 'assign_terms');
        if ($can_assign instanceof \WP_Error) {
            return $can_assign;
        }

        if (!\is_object_in_taxonomy($post->post_type, $taxonomy)) {
            return new \WP_Error(
                'bac_taxonomy_mismatch',
                \sprintf('Taxonomy "%s" is not attached to post type "%s".', $taxonomy, $post->post_type),
                ['status' => 400]
            );
        }

        $term_ids = \array_map('intval', (array) $input['term_ids']);
        foreach ($term_ids as $term_id) {
            $term = $this->requireTerm($taxonomy, $term_id);
            if ($term instanceof \WP_Error) {
                return $term;
            }
        }

        $result = \wp_set_object_terms($post->ID, $term_ids, $taxonomy, false);
        if ($result instanceof \WP_Error) {
            return $result;
        }

        $assigned_terms = \get_the_terms($post, $taxonomy);
        if ($assigned_terms instanceof \WP_Error) {
            return $assigned_terms;
        }

        return $this->actionResult(
            $post->ID,
            'content-terms',
            'Terms assigned successfully.',
            [
                'post_id' => (int) $post->ID,
                'post_type' => $post->post_type,
                'taxonomy' => $taxonomy,
                'terms' => empty($assigned_terms)
                    ? []
                    : \array_map(fn(\WP_Term $term) => $this->formatTerm($term), $assigned_terms),
            ]
        );
    }

    /**
     * List media items.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>
     */
    private function listMedia(array $input): array {
        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $per_page = isset($input['per_page']) ? (int) $input['per_page'] : 20;

        $query = new \WP_Query(
            [
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => $per_page,
                'paged' => $page,
                's' => $input['search'] ?? '',
                'author' => isset($input['author']) ? (int) $input['author'] : null,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => false,
            ]
        );

        $items = \array_map(
            fn(\WP_Post $post) => $this->formatMedia($post),
            \array_values(
                \array_filter(
                    $query->posts,
                    static fn(\WP_Post $post): bool => \current_user_can('edit_post', $post->ID)
                )
            )
        );

        return $this->listResult($items, \count($items), $page, $per_page);
    }

    /**
     * Get a media item.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function getMedia(array $input) {
        $attachment = $this->requireAttachment((int) $input['id']);
        if ($attachment instanceof \WP_Error) {
            return $attachment;
        }

        $can_edit = $this->requirePostCapability('edit_post', (int) $attachment->ID);
        if ($can_edit instanceof \WP_Error) {
            return $can_edit;
        }

        return $this->formatMedia($attachment);
    }

    /**
     * Create a media item from a source URL.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function createMedia(array $input) {
        $this->loadMediaFunctions();

        $source_url = (string) $input['source_url'];
        if (!\wp_http_validate_url($source_url)) {
            return new \WP_Error(
                'bac_invalid_source_url',
                'Media source_url must be a valid, safe HTTP or HTTPS URL.',
                ['status' => 400]
            );
        }
        if (isset($input['parent']) && (int) $input['parent'] > 0) {
            $can_edit_parent = $this->requirePostCapability('edit_post', (int) $input['parent']);
            if ($can_edit_parent instanceof \WP_Error) {
                return $can_edit_parent;
            }
        }

        $tmp_file = \download_url($source_url, 30);
        if ($tmp_file instanceof \WP_Error) {
            return $tmp_file;
        }

        $file_array = [
            'name' => \wp_basename((string) \wp_parse_url($source_url, PHP_URL_PATH) ?: 'remote-file'),
            'tmp_name' => $tmp_file,
        ];

        $attachment_id = \media_handle_sideload(
            $file_array,
            isset($input['parent']) ? (int) $input['parent'] : 0,
            $input['description'] ?? null
        );

        if ($attachment_id instanceof \WP_Error) {
            @\unlink($tmp_file);
            return $attachment_id;
        }

        $update_input = ['id' => (int) $attachment_id];
        foreach (['title', 'alt_text', 'caption', 'description'] as $field) {
            if (\array_key_exists($field, $input)) {
                $update_input[$field] = $input[$field];
            }
        }
        if (\count($update_input) > 1) {
            $update_result = $this->updateMedia($update_input);
            if ($update_result instanceof \WP_Error) {
                return $update_result;
            }
        }

        $attachment = $this->requireAttachment((int) $attachment_id);
        if ($attachment instanceof \WP_Error) {
            return $attachment;
        }

        return $this->actionResult(
            (int) $attachment_id,
            'media',
            'Media created successfully.',
            $this->formatMedia($attachment)
        );
    }

    /**
     * Update a media item.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function updateMedia(array $input) {
        $attachment = $this->requireAttachment((int) $input['id']);
        if ($attachment instanceof \WP_Error) {
            return $attachment;
        }

        $can_edit = $this->requirePostCapability('edit_post', (int) $attachment->ID);
        if ($can_edit instanceof \WP_Error) {
            return $can_edit;
        }

        $postarr = ['ID' => $attachment->ID];
        if (\array_key_exists('title', $input)) {
            $postarr['post_title'] = $input['title'];
        }
        if (\array_key_exists('caption', $input)) {
            $postarr['post_excerpt'] = $input['caption'];
        }
        if (\array_key_exists('description', $input)) {
            $postarr['post_content'] = $input['description'];
        }

        if (\count($postarr) > 1) {
            $updated = \wp_update_post($postarr, true);
            if ($updated instanceof \WP_Error) {
                return $updated;
            }
        }

        if (\array_key_exists('alt_text', $input)) {
            if (!\current_user_can('edit_post_meta', $attachment->ID, '_wp_attachment_image_alt')) {
                return $this->forbidden('edit_post_meta');
            }
            \update_post_meta($attachment->ID, '_wp_attachment_image_alt', (string) $input['alt_text']);
        }

        $fresh = $this->requireAttachment($attachment->ID);
        if ($fresh instanceof \WP_Error) {
            return $fresh;
        }

        return $this->actionResult(
            $attachment->ID,
            'media',
            'Media updated successfully.',
            $this->formatMedia($fresh)
        );
    }

    /**
     * Delete a media item.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function deleteMedia(array $input) {
        $attachment = $this->requireAttachment((int) $input['id']);
        if ($attachment instanceof \WP_Error) {
            return $attachment;
        }

        $can_delete = $this->requirePostCapability('delete_post', (int) $attachment->ID);
        if ($can_delete instanceof \WP_Error) {
            return $can_delete;
        }

        $deleted = \wp_delete_attachment($attachment->ID, !empty($input['force']));
        if (!$deleted instanceof \WP_Post) {
            return new \WP_Error(
                'bac_media_delete_failed',
                \sprintf('Failed to delete media ID %d.', $attachment->ID),
                ['status' => 500]
            );
        }

        return $this->actionResult(
            $attachment->ID,
            'media',
            'Media deleted successfully.',
            $this->formatMedia($attachment)
        );
    }

    /**
     * Format an attachment entity.
     *
     * @param \WP_Post $attachment Attachment post object.
     * @return array<string,mixed>
     */
    private function formatMedia(\WP_Post $attachment): array {
        return [
            'id' => (int) $attachment->ID,
            'type' => 'media',
            'status' => $attachment->post_status,
            'title' => $attachment->post_title,
            'caption' => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'author' => (int) $attachment->post_author,
            'mime_type' => \get_post_mime_type($attachment),
            'source_url' => \wp_get_attachment_url($attachment->ID),
            'alt_text' => (string) \get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
            'date_gmt' => $attachment->post_date_gmt,
        ];
    }

    /**
     * List users.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>
     */
    private function listUsers(array $input): array {
        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $per_page = isset($input['per_page']) ? (int) $input['per_page'] : 20;

        $query = new \WP_User_Query(
            [
                'number' => $per_page,
                'offset' => ($page - 1) * $per_page,
                'search' => !empty($input['search']) ? '*' . $input['search'] . '*' : '',
                'search_columns' => ['user_login', 'user_email', 'display_name'],
                'role' => $input['role'] ?? '',
            ]
        );

        $users = $query->get_results();
        $items = \array_map(fn(\WP_User $user) => $this->formatUser($user), $users);

        return $this->listResult($items, (int) $query->get_total(), $page, $per_page);
    }

    /**
     * Get a user entity.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function getUserEntity(array $input) {
        $user = $this->requireUser((int) $input['id']);
        if ($user instanceof \WP_Error) {
            return $user;
        }

        return $this->formatUser($user);
    }

    /**
     * Create a user.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function createUser(array $input) {
        $can_set_role = $this->requireUserRolePermission($input);
        if ($can_set_role instanceof \WP_Error) {
            return $can_set_role;
        }

        $userdata = [
            'user_login' => $input['username'],
            'user_email' => $input['email'],
            'user_pass' => $input['password'],
        ];
        foreach (['role', 'display_name', 'first_name', 'last_name'] as $field) {
            if (\array_key_exists($field, $input)) {
                $userdata[$field] = $input[$field];
            }
        }

        $user_id = \wp_insert_user($userdata);
        if ($user_id instanceof \WP_Error) {
            return $user_id;
        }

        $user = $this->requireUser((int) $user_id);
        if ($user instanceof \WP_Error) {
            return $user;
        }

        return $this->actionResult(
            (int) $user_id,
            'user',
            'User created successfully.',
            $this->formatUser($user)
        );
    }

    /**
     * Update a user.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function updateUser(array $input) {
        $user = $this->requireUser((int) $input['id']);
        if ($user instanceof \WP_Error) {
            return $user;
        }

        if (!\current_user_can('edit_user', $user->ID)) {
            return $this->forbidden('edit_user');
        }
        $can_set_role = $this->requireUserRolePermission($input, $user);
        if ($can_set_role instanceof \WP_Error) {
            return $can_set_role;
        }

        $userdata = ['ID' => $user->ID];
        $map = [
            'username' => 'user_login',
            'email' => 'user_email',
            'password' => 'user_pass',
            'role' => 'role',
            'display_name' => 'display_name',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
        ];
        foreach ($map as $input_key => $user_key) {
            if (\array_key_exists($input_key, $input)) {
                $userdata[$user_key] = $input[$input_key];
            }
        }

        $updated = \wp_update_user($userdata);
        if ($updated instanceof \WP_Error) {
            return $updated;
        }

        $fresh = $this->requireUser((int) $user->ID);
        if ($fresh instanceof \WP_Error) {
            return $fresh;
        }

        return $this->actionResult(
            $user->ID,
            'user',
            'User updated successfully.',
            $this->formatUser($fresh)
        );
    }

    /**
     * Delete a user.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function deleteUser(array $input) {
        $this->loadUserFunctions();

        $user = $this->requireUser((int) $input['id']);
        if ($user instanceof \WP_Error) {
            return $user;
        }

        if (!\current_user_can('delete_user', $user->ID)) {
            return $this->forbidden('delete_user');
        }
        $reassign = isset($input['reassign']) ? (int) $input['reassign'] : null;
        if ($reassign !== null) {
            $reassign_user = $this->requireUser($reassign);
            if ($reassign_user instanceof \WP_Error) {
                return $reassign_user;
            }
        }

        $deleted = \wp_delete_user($user->ID, $reassign);
        if (!$deleted) {
            return new \WP_Error(
                'bac_user_delete_failed',
                \sprintf('Failed to delete user ID %d.', $user->ID),
                ['status' => 500]
            );
        }

        return $this->actionResult(
            $user->ID,
            'user',
            'User deleted successfully.',
            $this->formatUser($user)
        );
    }

    /**
     * Format a user entity.
     *
     * @param \WP_User $user User object.
     * @return array<string,mixed>
     */
    private function formatUser(\WP_User $user): array {
        return [
            'id' => (int) $user->ID,
            'type' => 'user',
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'roles' => \array_values($user->roles),
            'registered' => $user->user_registered,
        ];
    }

    /**
     * List comments.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>
     */
    private function listComments(array $input): array {
        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $per_page = isset($input['per_page']) ? (int) $input['per_page'] : 20;
        $offset = ($page - 1) * $per_page;

        $query = new \WP_Comment_Query();
        $comments = $query->query(
            [
                'number' => $per_page,
                'offset' => $offset,
                'status' => $input['status'] ?? 'all',
                'post_id' => isset($input['post_id']) ? (int) $input['post_id'] : 0,
                'search' => $input['search'] ?? '',
            ]
        );

        $count_query = new \WP_Comment_Query();
        $count = (int) $count_query->query(
            [
                'count' => true,
                'status' => $input['status'] ?? 'all',
                'post_id' => isset($input['post_id']) ? (int) $input['post_id'] : 0,
                'search' => $input['search'] ?? '',
            ]
        );

        $items = \array_map(
            fn(\WP_Comment $comment) => $this->formatComment($comment),
            \array_values(
                \array_filter(
                    $comments,
                    static fn(\WP_Comment $comment): bool => \current_user_can('edit_comment', $comment->comment_ID)
                )
            )
        );

        return $this->listResult($items, \count($items), $page, $per_page);
    }

    /**
     * Get a comment entity.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function getCommentEntity(array $input) {
        $comment = $this->requireComment((int) $input['id']);
        if ($comment instanceof \WP_Error) {
            return $comment;
        }

        if (!\current_user_can('edit_comment', $comment->comment_ID)) {
            return $this->forbidden('edit_comment');
        }

        return $this->formatComment($comment);
    }

    /**
     * Create a comment.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function createComment(array $input) {
        if (!\current_user_can('moderate_comments')) {
            return $this->forbidden('moderate_comments');
        }

        $post = \get_post((int) $input['post_id']);
        if (!$post instanceof \WP_Post) {
            return $this->notFound('post', (int) $input['post_id']);
        }
        $can_edit_post = $this->requirePostCapability('edit_post', (int) $post->ID);
        if ($can_edit_post instanceof \WP_Error) {
            return $can_edit_post;
        }

        $commentdata = [
            'comment_post_ID' => (int) $input['post_id'],
            'comment_content' => (string) $input['content'],
        ];
        $map = [
            'status' => 'comment_approved',
            'author_name' => 'comment_author',
            'author_email' => 'comment_author_email',
            'author_url' => 'comment_author_url',
            'user_id' => 'user_id',
            'parent' => 'comment_parent',
        ];
        foreach ($map as $input_key => $comment_key) {
            if (\array_key_exists($input_key, $input)) {
                $commentdata[$comment_key] = $input[$input_key];
            }
        }

        $comment_id = \wp_insert_comment($commentdata);
        if (!$comment_id) {
            return new \WP_Error(
                'bac_comment_create_failed',
                'Failed to create comment.',
                ['status' => 500]
            );
        }

        $comment = $this->requireComment((int) $comment_id);
        if ($comment instanceof \WP_Error) {
            return $comment;
        }

        return $this->actionResult(
            (int) $comment_id,
            'comment',
            'Comment created successfully.',
            $this->formatComment($comment)
        );
    }

    /**
     * Update a comment.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function updateComment(array $input) {
        $comment = $this->requireComment((int) $input['id']);
        if ($comment instanceof \WP_Error) {
            return $comment;
        }

        if (!\current_user_can('edit_comment', $comment->comment_ID)) {
            return $this->forbidden('edit_comment');
        }
        foreach (['status', 'author_name', 'author_email', 'author_url', 'user_id'] as $moderated_field) {
            if (\array_key_exists($moderated_field, $input) && !\current_user_can('moderate_comments')) {
                return $this->forbidden('moderate_comments');
            }
        }

        $commentdata = ['comment_ID' => $comment->comment_ID];
        $map = [
            'content' => 'comment_content',
            'status' => 'comment_approved',
            'author_name' => 'comment_author',
            'author_email' => 'comment_author_email',
            'author_url' => 'comment_author_url',
            'user_id' => 'user_id',
            'parent' => 'comment_parent',
        ];
        foreach ($map as $input_key => $comment_key) {
            if (\array_key_exists($input_key, $input)) {
                $commentdata[$comment_key] = $input[$input_key];
            }
        }

        $result = \wp_update_comment($commentdata, true);
        if ($result instanceof \WP_Error) {
            return $result;
        }
        if (false === $result) {
            return new \WP_Error(
                'bac_comment_update_failed',
                \sprintf('Failed to update comment ID %d.', $comment->comment_ID),
                ['status' => 500]
            );
        }

        $fresh = $this->requireComment($comment->comment_ID);
        if ($fresh instanceof \WP_Error) {
            return $fresh;
        }

        return $this->actionResult(
            $comment->comment_ID,
            'comment',
            'Comment updated successfully.',
            $this->formatComment($fresh)
        );
    }

    /**
     * Delete a comment.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function deleteComment(array $input) {
        $comment = $this->requireComment((int) $input['id']);
        if ($comment instanceof \WP_Error) {
            return $comment;
        }

        if (!\current_user_can('delete_comment', $comment->comment_ID)) {
            return $this->forbidden('delete_comment');
        }

        $deleted = \wp_delete_comment($comment->comment_ID, !empty($input['force']));
        if (!$deleted) {
            return new \WP_Error(
                'bac_comment_delete_failed',
                \sprintf('Failed to delete comment ID %d.', $comment->comment_ID),
                ['status' => 500]
            );
        }

        return $this->actionResult(
            $comment->comment_ID,
            'comment',
            'Comment deleted successfully.',
            $this->formatComment($comment)
        );
    }

    /**
     * Format a comment entity.
     *
     * @param \WP_Comment $comment Comment object.
     * @return array<string,mixed>
     */
    private function formatComment(\WP_Comment $comment): array {
        return [
            'id' => (int) $comment->comment_ID,
            'type' => 'comment',
            'post_id' => (int) $comment->comment_post_ID,
            'status' => (string) $comment->comment_approved,
            'author_name' => $comment->comment_author,
            'author_email' => $comment->comment_author_email,
            'author_url' => $comment->comment_author_url,
            'user_id' => (int) $comment->user_id,
            'parent' => (int) $comment->comment_parent,
            'content' => $comment->comment_content,
            'date_gmt' => $comment->comment_date_gmt,
        ];
    }

    /**
     * List installed plugins.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>
     */
    private function listPlugins(array $input): array {
        $this->loadPluginFunctions();

        $all_plugins = \get_plugins();
        $items = [];
        $status_filter = $input['status'] ?? 'all';
        $search = isset($input['search']) ? \strtolower((string) $input['search']) : '';

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $entity = $this->formatPlugin($plugin_file, $plugin_data);

            if ('active' === $status_filter && 'active' !== $entity['status']) {
                continue;
            }
            if ('inactive' === $status_filter && 'inactive' !== $entity['status']) {
                continue;
            }
            if ('' !== $search) {
                $haystack = \strtolower($entity['plugin'] . ' ' . $entity['name'] . ' ' . $entity['description']);
                if (false === \strpos($haystack, $search)) {
                    continue;
                }
            }

            $items[] = $entity;
        }

        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $per_page = isset($input['per_page']) ? (int) $input['per_page'] : 50;
        $total = \count($items);
        $items = \array_slice($items, ($page - 1) * $per_page, $per_page);

        return $this->listResult($items, $total, $page, $per_page);
    }

    /**
     * Get a plugin entity.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function getPluginEntity(array $input) {
        $plugin_file = $this->requirePluginFile((string) $input['plugin']);
        if ($plugin_file instanceof \WP_Error) {
            return $plugin_file;
        }

        $plugins = \get_plugins();
        return $this->formatPlugin($plugin_file, $plugins[$plugin_file]);
    }

    /**
     * Activate an installed plugin.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function activatePluginAbility(array $input) {
        $plugin_file = $this->requirePluginFile((string) $input['plugin']);
        if ($plugin_file instanceof \WP_Error) {
            return $plugin_file;
        }

        $result = \activate_plugin($plugin_file, '', false, false);
        if ($result instanceof \WP_Error) {
            return $result;
        }

        $plugins = \get_plugins();
        return $this->actionResult(
            $plugin_file,
            'plugin',
            'Plugin activated successfully.',
            $this->formatPlugin($plugin_file, $plugins[$plugin_file])
        );
    }

    /**
     * Deactivate an installed plugin.
     *
     * @param array $input Ability input.
     * @return array<string,mixed>|\WP_Error
     */
    private function deactivatePluginAbility(array $input) {
        $plugin_file = $this->requirePluginFile((string) $input['plugin']);
        if ($plugin_file instanceof \WP_Error) {
            return $plugin_file;
        }

        \deactivate_plugins($plugin_file, false, false);

        $plugins = \get_plugins();
        return $this->actionResult(
            $plugin_file,
            'plugin',
            'Plugin deactivated successfully.',
            $this->formatPlugin($plugin_file, $plugins[$plugin_file])
        );
    }

    /**
     * Format a plugin entity.
     *
     * @param string $plugin_file Plugin file path.
     * @param array  $plugin_data Plugin header data.
     * @return array<string,mixed>
     */
    private function formatPlugin(string $plugin_file, array $plugin_data): array {
        return [
            'plugin' => $plugin_file,
            'type' => 'plugin',
            'status' => \is_plugin_active($plugin_file)
                ? 'active'
                : (\is_multisite() && \is_plugin_active_for_network($plugin_file) ? 'network-active' : 'inactive'),
            'name' => $plugin_data['Name'] ?? '',
            'version' => $plugin_data['Version'] ?? '',
            'description' => \wp_strip_all_tags($plugin_data['Description'] ?? ''),
            'author' => \wp_strip_all_tags($plugin_data['Author'] ?? ''),
            'text_domain' => $plugin_data['TextDomain'] ?? '',
        ];
    }
}
