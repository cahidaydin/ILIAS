<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author  Niels Theen <ntheen@databay.de>
 */
class ilExercisePlaceholderValuesTest extends ilCertificateBaseTestCase
{
    public function testGetPlaceholderValues()
    {
        $defaultPlaceholders = $this->getMockBuilder(ilDefaultPlaceholderValues::class)
            ->disableOriginalConstructor()
            ->getMock();

        $language = $this->getMockBuilder(ilLanguage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $language->method('txt')
            ->willReturn('Some Translation');

        $objectMock = $this->getMockBuilder(ilObject::class)
            ->disableOriginalConstructor()
            ->getMock();

        $objectMock->expects($this->once())
            ->method('getTitle')
            ->willReturn('Some Title');

        $objectHelper = $this->getMockBuilder(ilCertificateObjectHelper::class)
            ->getMock();

        $objectHelper->expects($this->once())
            ->method('getInstanceByObjId')
            ->with(200)
            ->willReturn($objectMock);

        $lpMarksHelper = $this->getMockBuilder(ilCertificateLPMarksHelper::class)
            ->getMock();

        $lpMarksHelper->expects($this->once())
            ->method('lookUpMark')
            ->willReturn('400');

        $exerciseMemberHelper = $this->getMockBuilder(ilCertificateExerciseMembersHelper::class)
            ->getMock();

        $lpStatusHelper = $this->getMockBuilder(ilCertificateLPStatusHelper::class)
            ->getMock();

        $lpStatusHelper->method('lookupStatusChanged')
            ->willReturn('aaa');

        $utilHelper = $this->getMockBuilder(ilCertificateUtilHelper::class)
            ->getMock();

        $utilHelper->expects($this->exactly(3))
            ->method('prepareFormOutput')
            ->willReturn('Some Formatted Output');

        $dateHelper = $this->getMockBuilder(ilCertificateDateHelper::class)
            ->getMock();

        $dateHelper->expects($this->once())
            ->method('formatDate')
            ->willReturn('2018-09-10');

        $dateHelper->expects($this->once())
            ->method('formatDateTime')
            ->willReturn('2018-09-10 12:01:33');


        $placeHolderObject = new ilExercisePlaceholderValues(
            $defaultPlaceholders,
            $language,
            $objectHelper,
            $lpMarksHelper,
            $exerciseMemberHelper,
            $lpStatusHelper,
            $utilHelper,
            $dateHelper
        );

        $result = $placeHolderObject->getPlaceholderValues(100, 200);

        $this->assertEquals(
            array(
                'RESULT_PASSED' => 'Some Formatted Output',
                'RESULT_MARK' => 'Some Formatted Output',
                'EXERCISE_TITLE' => 'Some Formatted Output',
                'DATE_COMPLETED' => '2018-09-10',
                'DATETIME_COMPLETED' => '2018-09-10 12:01:33'
            ),
            $result
        );
    }

    public function testGetPlaceholderValuesForPreview()
    {
        $defaultPlaceholders = $this->getMockBuilder(ilDefaultPlaceholderValues::class)
            ->disableOriginalConstructor()
            ->getMock();

        $language = $this->getMockBuilder(ilLanguage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $language->method('txt')
            ->willReturn('Something');

        $objectMock = $this->getMockBuilder(ilObject::class)
            ->disableOriginalConstructor()
            ->getMock();

        $objectMock->method('getTitle')
            ->willReturn('SomeTitle');

        $objectHelper = $this->getMockBuilder(ilCertificateObjectHelper::class)
            ->getMock();

        $objectHelper->method('getInstanceByObjId')
            ->willReturn($objectMock);

        $lpMarksHelper = $this->getMockBuilder(ilCertificateLPMarksHelper::class)
            ->getMock();

        $exerciseMemberHelper = $this->getMockBuilder(ilCertificateExerciseMembersHelper::class)
            ->getMock();

        $lpStatusHelper = $this->getMockBuilder(ilCertificateLPStatusHelper::class)
            ->getMock();

        $utilHelper = $this->getMockBuilder(ilCertificateUtilHelper::class)
            ->getMock();

        $utilHelper->method('prepareFormOutput')
            ->willReturnCallback(function ($input) {
                return $input;
            });

        $dateHelper = $this->getMockBuilder(ilCertificateDateHelper::class)
            ->getMock();

        $defaultPlaceholders
            ->expects($this->atLeastOnce())
            ->method('getPlaceholderValuesForPreview')
            ->willReturn(array('SOME_PLACEHOLDER' => 'something'));

        $placeHolderObject = new ilExercisePlaceholderValues(
            $defaultPlaceholders,
            $language,
            $objectHelper,
            $lpMarksHelper,
            $exerciseMemberHelper,
            $lpStatusHelper,
            $utilHelper,
            $dateHelper
        );

        $result = $placeHolderObject->getPlaceholderValuesForPreview(100, 10);

        $this->assertEquals(
            array(
                'SOME_PLACEHOLDER' => 'something',
                'RESULT_PASSED' => 'Something',
                'EXERCISE_TITLE' => 'SomeTitle',
                'RESULT_MARK' => 'Something'
            ),
            $result
        );
    }
}
