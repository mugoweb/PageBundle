<?php

namespace Mugo\PageBundle\Mapper;

use Ibexa\AdminUi\FieldType\FieldDefinitionFormMapperInterface;
use Ibexa\AdminUi\Form\Data\FieldDefinitionData;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * FormMapper for mugopage FieldType.
 */
class MugoPageFormDefinitionMapper implements FieldDefinitionFormMapperInterface
{
    public function mapFieldDefinitionForm(FormInterface $fieldDefinitionForm, FieldDefinitionData $data): void
    {
        $isTranslation = $data->contentTypeData->languageCode !== $data->contentTypeData->mainLanguageCode;
        $fieldDefinitionForm
            ->add(
                'textRows',
                IntegerType::class,
                [
                    'required' => false,
                    'property_path' => 'fieldSettings[textRows]',
                    'label' => /** @Desc("Number of text rows") */ 'field_definition.mugopage.text_rows',
                    'disabled' => $isTranslation,
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'translation_domain' => 'content_type',
            ]);
    }

}
