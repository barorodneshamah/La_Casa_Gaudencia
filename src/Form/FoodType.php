<?php

namespace App\Form;

use App\Entity\Food;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class FoodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Food Name',
                'attr' => [
                    'placeholder' => 'e.g., Grilled Salmon, Caesar Salad',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a food name']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Describe the dish, its ingredients, and flavor profile...',
                    'rows' => 4,
                    'class' => 'form-control',
                ],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Price',
                'currency' => 'PHP',
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a price']),
                    new Positive(['message' => 'Price must be a positive number']),
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Category',
                'placeholder' => 'Select a category',
                'choices' => [
                    'Appetizer' => 'Appetizer',
                    'Main Course' => 'Main Course',
                    'Dessert' => 'Dessert',
                    'Beverage' => 'Beverage',
                    'Snack' => 'Snack',
                    'Breakfast' => 'Breakfast',
                    'Soup' => 'Soup',
                    'Salad' => 'Salad',
                    'Pasta' => 'Pasta',
                    'Pizza' => 'Pizza',
                    'Seafood' => 'Seafood',
                    'Grilled' => 'Grilled',
                    'Sandwich' => 'Sandwich',
                    'Rice Meal' => 'Rice Meal',
                    'Filipino' => 'Filipino',
                    'Asian' => 'Asian',
                    'Western' => 'Western',
                    'Vegetarian' => 'Vegetarian',
                    'Kids Meal' => 'Kids Meal',
                    'Special' => 'Special',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a category']),
                ],
            ])
            ->add('availableStock', IntegerType::class, [
                'label' => 'Available Stock',
                'attr' => [
                    'placeholder' => '0',
                    'min' => 0,
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter available Stock']),
                    new PositiveOrZero(['message' => 'Stock cannot be negative']),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Available' => 'Available',
                    'Out of Stock' => 'Out of Stock',
                    'Limited' => 'Limited',
                    'Coming Soon' => 'Coming Soon',
                    'Discontinued' => 'Discontinued',
                    'Seasonal' => 'Seasonal',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a status']),
                ],
            ])
            ->add('mainImageFile', FileType::class, [
                'label' => 'Main Food Image',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/*',
                    'class' => 'file-input',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, WebP, or GIF)',
                    ]),
                ],
            ])
            ->add('galleryImageFiles', FileType::class, [
                'label' => 'Gallery Images',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => [
                    'accept' => 'image/*',
                    'class' => 'file-input',
                ],
                'constraints' => [
                    new All([
                        'constraints' => [
                            new File([
                                'maxSize' => '5M',
                                'mimeTypes' => [
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                    'image/gif',
                                ],
                                'mimeTypesMessage' => 'Please upload valid images (JPEG, PNG, WebP, or GIF)',
                            ]),
                        ],
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Food::class,
        ]);
    }
}