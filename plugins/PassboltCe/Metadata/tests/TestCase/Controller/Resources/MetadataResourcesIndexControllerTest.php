<?php
declare(strict_types=1);

/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SA (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SA (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         4.10.0
 */

namespace Passbolt\Metadata\Test\TestCase\Controller\Resources;

use App\Test\Factory\ResourceFactory;
use App\Test\Lib\AppIntegrationTestCaseV5;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Passbolt\Metadata\Model\Dto\MetadataResourceDto;

class MetadataResourcesIndexControllerTest extends AppIntegrationTestCaseV5
{
    public function testMetadataResourcesIndexController_Metadata_Enabled_Success(): void
    {
        $user = $this->logInAsUser();
        ResourceFactory::make()
            ->withPermissionsFor([$user])
            ->setField('modified', FrozenTime::yesterday())
            ->persist();
        ResourceFactory::make()
            ->withPermissionsFor([$user])
            ->v5Fields()
            ->setField('modified', FrozenTime::now())
            ->persist();

        $this->getJson('/resources.json?sort=Resources.modified&direction=asc');
        $this->assertSuccess();
        $response = $this->_responseJsonBody;

        $resourceV4 = $response[0];
        $resourceV5 = $response[1];

        $this->assertObjectNotHasAttributes(MetadataResourceDto::V4_META_PROPS, $resourceV5);
        $this->assertObjectNotHasAttributes(MetadataResourceDto::V5_META_PROPS, $resourceV4);
        $this->assertObjectHasAttributes(MetadataResourceDto::V5_META_PROPS, $resourceV5);
        $this->assertObjectHasAttributes(MetadataResourceDto::V4_META_PROPS, $resourceV4);
    }

    public function testMetadataResourcesIndexController_Metadata_Disabled_Success(): void
    {
        Configure::write('passbolt.v5.enabled', false);

        $user = $this->logInAsUser();
        ResourceFactory::make()->withPermissionsFor([$user])->persist();
        ResourceFactory::make()->withPermissionsFor([$user])->v5Fields()->persist();

        $this->getJson('/resources.json');
        $this->assertSuccess();
        $response = json_decode(json_encode($this->_responseJsonBody), true);
        $this->assertCount(1, $response);
        $resourceV4 = array_pop($response);
        $this->assertArrayHasAttributes(MetadataResourceDto::V4_META_PROPS, $resourceV4);
    }

    public function metadataKeyType()
    {
        return [
            ['user_key'],
            ['shared_key'],
        ];
    }

    /**
     * @dataProvider metadataKeyType
     */
    public function testMetadataResourcesIndexController_Metadata_Enabled_Filter_MetadataKeyType(string $metadataKeyType): void
    {
        $user = $this->logInAsUser();
        ResourceFactory::make()
            ->withPermissionsFor([$user])
            ->v5Fields()
            ->persist();
        ResourceFactory::make()
            ->withPermissionsFor([$user])
            ->v5Fields(true)
            ->persist();

        $this->getJson("/resources.json?filter[metadata_key_type]=$metadataKeyType");
        $this->assertSuccess();
        $response = json_decode(json_encode($this->_responseJsonBody), true);
        $resource = $response[0];
        $this->assertSame($metadataKeyType, $resource['metadata_key_type']);
        $this->assertCount(1, $response);
    }

    public function testMetadataResourcesIndexController_Metadata_Disabled_Filter_MetadataKeyType(): void
    {
        Configure::write('passbolt.v5.enabled', false);

        $user = $this->logInAsUser();
        ResourceFactory::make(2)->withPermissionsFor([$user])->persist();

        $this->getJson('/resources.json?filter[metadata_key_type]=user_key');
        $this->assertSuccess();
        $response = json_decode(json_encode($this->_responseJsonBody), true);
        $this->assertCount(2, $response);
    }

    public function testMetadataResourcesIndexController_Metadata_Enabled_Filter_MetadataKeyType_Invalid_Value(): void
    {
        $this->logInAsUser();
        $this->getJson('/resources.json?filter[metadata_key_type]=foo');
        $this->assertBadRequestError('Invalid filter. "foo" is not a valid value for filter metadata_key_type.');
    }
}
