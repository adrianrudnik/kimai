<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Type;

use App\Configuration\SystemConfiguration;
use App\Entity\Activity;
use App\Repository\ActivityRepository;
use App\Repository\Query\ActivityFormTypeQuery;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Custom form field type to select an activity.
 */
class ActivityType extends AbstractType
{
    public const PATTERN_NAME = '{name}';
    public const PATTERN_COMMENT = '{comment}';
    public const PATTERN_SPACER = '{spacer}';
    public const SPACER = ' - ';

    private $configuration;
    private $pattern;

    public function __construct(SystemConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getChoiceLabel(Activity $activity): string
    {
        if ($this->pattern === null) {
            $this->pattern = $this->configuration->find('activity.choice_pattern');

            if ($this->pattern === null || stripos($this->pattern, '{') === false || stripos($this->pattern, '}') === false) {
                $this->pattern = self::PATTERN_NAME;
            }
        }

        $name = $this->pattern;
        $name = str_replace(self::PATTERN_NAME, $activity->getName(), $name);
        $name = str_replace(self::PATTERN_COMMENT, $activity->getComment(), $name);
        $name = str_replace(self::PATTERN_SPACER, self::SPACER, $name);

        $name = ltrim($name, self::SPACER);
        $name = rtrim($name, self::SPACER);

        if ($name === '' || $name === self::SPACER) {
            $name = $activity->getName();
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public function groupBy(Activity $activity, $key, $index)
    {
        if (null === $activity->getProject()) {
            return null;
        }

        return $activity->getProject()->getName();
    }

    /**
     * @param Activity $activity
     * @param string $key
     * @param mixed $value
     * @return array
     */
    public function getChoiceAttributes(Activity $activity, $key, $value)
    {
        if (null !== ($project = $activity->getProject())) {
            return ['data-project' => $project->getId(), 'data-currency' => $project->getCustomer()->getCurrency()];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // documentation is for NelmioApiDocBundle
            'documentation' => [
                'type' => 'integer',
                'description' => 'Activity ID',
            ],
            'label' => 'label.activity',
            'class' => Activity::class,
            'choice_label' => [$this, 'getChoiceLabel'],
            'choice_attr' => [$this, 'getChoiceAttributes'],
            'group_by' => [$this, 'groupBy'],
            'query_builder_for_user' => true,
            // @var Project|Project[]|int|int[]|null
            'projects' => null,
            // @var Activity|Activity[]|int|int[]|null
            'activities' => null,
            // @var Activity|null
            'ignore_activity' => null,
        ]);

        $resolver->setDefault('query_builder', function (Options $options) {
            return function (ActivityRepository $repo) use ($options) {
                $query = new ActivityFormTypeQuery($options['activities'], $options['projects']);

                if (true === $options['query_builder_for_user']) {
                    $query->setUser($options['user']);
                }

                if (null !== $options['ignore_activity']) {
                    $query->setActivityToIgnore($options['ignore_activity']);
                }

                return $repo->getQueryBuilderForFormType($query);
            };
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return EntityType::class;
    }
}
