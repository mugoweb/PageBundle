<?php

namespace MugoWeb\PageBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Ibexa\Core\Repository\SiteAccessAware\Repository;

// php bin/console mugopage:ezflow:remove-legacy-fields --env=ENV --siteaccess=SITE
class RemoveLegacyFieldsCommand extends Command {

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * Dependency Injection via constructor.
     * Note: ContainerInterface removed as it is a best practice not to inject the whole container.
     */
    public function __construct(Repository $repository) {
        parent::__construct(null);
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this
            ->setName('mugopage:ezflow:remove-legacy-fields')
            ->addOption(
                'script_user', 'u', InputOption::VALUE_OPTIONAL, 'Ibexa username (with Role containing at least ContentType policies: read, edit)', 'admin'
            )
            ->setDescription('Remove legacy eZ Flow fields');
    }

    protected function initialize(InputInterface $input, OutputInterface $output) {
        parent::initialize($input, $output);

        // Log in the specified user to bypass permission restrictions
        $this->repository->getPermissionResolver()->setCurrentUserReference(
            $this->repository->getUserService()->loadUserByLogin($input->getOption('script_user'))
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln('Starting removing ez flow legacy fields from content types...');

        $contentTypeService = $this->repository->getContentTypeService();
        $contentTypeGroups = $contentTypeService->loadContentTypeGroups();

        foreach ($contentTypeGroups as $contentTypeGroup) {
            $contentTypes = $contentTypeService->loadContentTypes($contentTypeGroup);

            foreach ($contentTypes as $contentType) {
                $hasEzPageField = false;

                // Check if the content type actually has an 'ezpage' field first
                foreach ($contentType->getFieldDefinitions() as $fieldDefinition) {
                    if ($fieldDefinition->fieldTypeIdentifier === 'ezpage') {
                        $hasEzPageField = true;
                        break;
                    }
                }

                // If no ezpage field is found, skip to save performance
                if (!$hasEzPageField) {
                    continue;
                }

                $output->writeln("Found 'ezpage' field in Content Type: <info>{$contentType->identifier}</info>. Processing...");

                // Wrap the repository operations in sudo to bypass potential permission locks
                $this->repository->sudo(function () use ($contentTypeService, $contentType, $output) {

                    // 1. Check if a draft already exists (ignoring ownership) and remove it
                    try {
                        // The first argument is the Content Type ID
                        // The SECOND argument is a boolean flag to ignore ownership!
                        $existingDraft = $contentTypeService->loadContentTypeDraft($contentType->id, true);

                        if ($existingDraft) {
                            $output->writeln(" -> Found an existing draft for '{$contentType->identifier}' owned by another user. Removing it...");
                            $contentTypeService->deleteContentTypeDraft($existingDraft);
                        }
                    } catch (\Ibexa\Core\Repository\Exceptions\NotFoundException | \Ibexa\Core\Persistence\Legacy\Exception\TypeNotFound $e) {
                        // No draft exists at all, so we can proceed safely!
                    }

                    // 2. Now it is safe to create a clean draft of the Content Type
                    $contentTypeDraft = $contentTypeService->createContentTypeDraft($contentType);
                    $fieldsRemoved = false;

                    // 3. Iterate and remove the field from the draft
                    foreach ($contentTypeDraft->getFieldDefinitions() as $draftFieldDefinition) {
                        if ($draftFieldDefinition->fieldTypeIdentifier === 'ezpage') {
                            $contentTypeService->removeFieldDefinition($contentTypeDraft, $draftFieldDefinition);
                            $output->writeln(" -> Removed field '<comment>{$draftFieldDefinition->identifier}</comment>' from draft.");
                            $fieldsRemoved = true;
                        }
                    }

                    // 4. Publish the draft if changes were made
                    if ($fieldsRemoved) {
                        $contentTypeService->publishContentTypeDraft($contentTypeDraft);
                        $output->writeln(" -> Published updated Content Type: <info>{$contentType->identifier}</info>");
                    }
                });
            }
        }

        $output->writeln('Finished processing Content Types.');
        return Command::SUCCESS;
    }
}