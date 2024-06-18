<?php

namespace Mugo\PageBundle\Form\Type\FieldType;

use Ibexa\ContentForms\FieldType\DataTransformer\FieldValueTransformer;
use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form Type representing mugopage field type.
 */
class MugoPageFieldType extends AbstractType
{
    /** @var \Ibexa\Contracts\Core\Repository\FieldTypeService */
    protected $fieldTypeService;

    public function __construct(FieldTypeService $fieldTypeService)
    {
        $this->fieldTypeService = $fieldTypeService;
    }

    public function getName()
    {
        return $this->getBlockPrefix();
    }

    public function getBlockPrefix()
    {
        return 'ezplatform_fieldtype_mugopage';
    }

    public function getParent()
    {
        return TextareaType::class;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (null !== $options['rows']) {
            $view->vars['attr'] = array_merge($view->vars['attr'], ['rows' => $options['rows']]);
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new FieldValueTransformer($this->fieldTypeService->getFieldType('mugopage')));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('rows', null)
            ->setAllowedTypes('rows', ['integer']);
    }
}
