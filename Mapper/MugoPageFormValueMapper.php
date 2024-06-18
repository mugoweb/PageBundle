<?php

namespace Mugo\PageBundle\Mapper;

use Symfony\Component\Form\FormInterface;
use Mugo\PageBundle\Form\Type\FieldType\MugoPageFieldType;
use Ibexa\Contracts\ContentForms\FieldType\FieldValueFormMapperInterface;
use Ibexa\Contracts\ContentForms\Data\Content\FieldData;


/**
 * FormMapper for mugopage FieldType.
 */
class MugoPageFormValueMapper implements FieldValueFormMapperInterface
{

    public function mapFieldValueForm(FormInterface $fieldForm, FieldData $data)
    {
        $fieldDefinition = $data->fieldDefinition;
        $formConfig = $fieldForm->getConfig();

        $fieldForm
            ->add(
                $formConfig->getFormFactory()->createBuilder()
                    ->create(
                        'value',
                        MugoPageFieldType::class,
                        [
                            'required' => $fieldDefinition->isRequired,
                            'label' => $fieldDefinition->getName(),
                            'rows' => $data->fieldDefinition->fieldSettings['textRows'],
                        ]
                    )
                    ->setAutoInitialize(false)
                    ->getForm()
            );
    }
}
