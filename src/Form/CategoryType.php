<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Form/CategoryType.php

namespace App\Form;

use App\Entity\Category;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Category|null $category */
        $category = $options['data'] ?? null;
        $categoryId = $category?->getId();

        $builder
            ->add('name', TextType::class, [
                'label' => 'categories.form.name',
                'required' => true,
                'attr' => [
                    'placeholder' => 'categories.form.name_placeholder',
                    'maxlength' => 100,
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'categories.form.slug',
                'required' => false,
                'help' => 'categories.form.slug_help',
                'attr' => [
                    'placeholder' => 'categories.form.slug_placeholder',
                    'maxlength' => 100,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'categories.form.description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'maxlength' => 510,
                    'placeholder' => 'categories.form.description_placeholder',
                ],
            ])
            ->add('parent', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'categories.form.parent',
                'required' => false,
                'placeholder' => 'categories.form.no_parent',
                'query_builder' => function (EntityRepository $er) use ($categoryId) {
                    $qb = $er->createQueryBuilder('c')
                        ->orderBy('c.name', 'ASC');

                    // prevent selecting itself as parent when editing an existing category
                    if ($categoryId !== null) {
                        $qb->andWhere('c.id != :currentId')
                            ->setParameter('currentId', $categoryId);
                    }

                    return $qb;
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
