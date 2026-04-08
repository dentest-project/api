<?php

declare(strict_types=1);

namespace App\Serializer;

enum Groups: string
{
    case ReadDomainModel = 'READ_DOMAIN_MODEL';

    case ListFeatures = 'LIST_FEATURES';

    case ListOrganizations = 'LIST_ORGANIZATIONS';

    case ListOrganizationIssueTrackerConfigurations = 'LIST_ORGANIZATION_ISSUE_TRACKER_CONFIGURATIONS';

    case ListOrganizationUsers = 'LIST_ORGANIZATION_USERS';

    case ListProjects = 'LIST_PROJECTS';

    case ListProjectUsers = 'LIST_PROJECT_USERS';

    case ListTags = 'LIST_TAGS';

    case ListUsers = 'LIST_USERS';

    case ReadFeature = 'READ_FEATURE';

    case ReadFeatureIssueTrackerConfiguration = 'READ_FEATURE_ISSUE_TRACKER_CONFIGURATION';

    case ReadOrganization = 'READ_ORGANIZATION';

    case ReadOrganizationIssueTrackerConfiguration = 'READ_ORGANIZATION_ISSUE_TRACKER_CONFIGURATION';

    case ReadOrganizationUser = 'READ_ORGANIZATION_USER';

    case ReadPath = 'READ_PATH';

    case ReadProject = 'READ_PROJECT';

    case ReadProjectUser = 'READ_PROJECT_USER';

    case ReadProjectUserToken = 'READ_PROJECT_USER_TOKEN';

    case ReadStep = 'READ_STEP';

    case ReadTag = 'READ_TAG';
}
