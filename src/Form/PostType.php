<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Form/PostType.php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Post;
use App\Enum\PostDiffusio;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'title',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'post title...',
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => '-- select category --',
                'label' => 'category',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'language',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-select',
                ],
                'choices' => [
                    'English' => 'en',
                    'Español' => 'es',
                    'Ἑλληνικά' => 'el',
                ],
                'data' => 'en',
            ])
            ->add('diffusio', EnumType::class, [
                'class' => PostDiffusio::class,
                'label' => 'visibility',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-select',
                ],
                'placeholder' => '-- none --',
                'required' => false,
            ])
            ->add('content', TextareaType::class, [
                'label' => 'content',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                    'placeholder' => 'write post content...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}
