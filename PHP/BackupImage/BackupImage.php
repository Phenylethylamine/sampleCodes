<?php
/**
 * Class BackupImage
 * 
 * (상품백업테이블) 테이블로 이관된 상품데이터에 대해
 * 상품이미지를 카피하고 삭제합니다
 *
 * (상품백업테이블).size_option 에 진행상태를 표기합니다
 * B : 백업완료
 * BD : 백업 후 삭제 완료
 * D : 백업 없이 삭제 완료
 */
class BackupImage
{
    public $ssh2, $curlForDelete, $curlForFileExists;
    public $copyCount = 0, $deleteCount = 0;


    public function exec($limit = 0)
    {
        ini_set('max_execution_time', 55);
        $auctionProductBackup = $this->getAuctionProductBackup($limit);
        checkTime('execStart');
        for ($i = 0, $end = count($auctionProductBackup); $i < $end; ++$i) {
            Dev::print('auctionProductBackupRow', $auctionProductBackup[$i]);
            $this->execAuctionProductRow($auctionProductBackup[$i]);
            if ((time() - $GLOBALS['TIME']) > 55) break;
        }
        checkTime('execEnd');
        Dev::print('copyCount',$this->copyCount);
        Dev::print('deleteCount',$this->deleteCount);
    }

    public function getAuctionProductBackup($limit)
    {
        $sql = "
            SELECT		APB.number,
                        APB.size_option,
                        AJ.NUMBER AS aj_number,
                        AJB.number AS ajb_number,
                        APB.img1, APB.img2, APB.img3, APB.img4, APB.img5,
                        APB.img6, APB.img7, APB.img8, APB.img9, APB.img10
            FROM		(상품백업테이블) AS APB
            LEFT OUTER JOIN (주문테이블) AS AJ ON APB.number = AJ.product_number AND AJ.product_stats NOT IN (1,2,99)
            LEFT OUTER JOIN (주문백업테이블) AS AJB ON APB.number = AJB.product_number AND AJB.product_stats NOT IN (1,2,99)
            WHERE		APB.img1 <> ''
            AND         APB.size_option IN ('','B')
            GROUP BY	APB.number
            HAVING		(AJ.number IS NOT NULL OR AJB.number IS NOT NULL)
            LIMIT		{$limit}
		";
        Dev::print('sql', $sql);
        return DB::query($sql);
    }

    public function execAuctionProductRow($auctionProductRow)
    {
        # 처리 이미지 목록(2차원 배열)
        $source = self::getSourceFromAuctionProductRow($auctionProductRow);
        Dev::print('sourceList', $source);

        # 판매이력이 있으면 이미지 복사
        $needCopy = (!empty($auctionProductRow['aj_number']) || !empty($auctionProductRow['ajb_number']));
        $isCopied = in_array($auctionProductRow['size_option'], array('B', 'BD'));

        Dev::print('needCopy', $needCopy);
        Dev::print('isCopied', $isCopied);

        if ($needCopy) {
            if ($isCopied) {
                $allCopySuccess = true;
            } else {
                $copyTarget = array_column($source, 0);
                $copyTargetExists = $this->fileExists($copyTarget);
                $copySuccessCount = 0;
                for ($i = 0, $end = count($copyTargetExists['exists']); $i < $end; ++$i) {
                    $copyResult = $this->copy($copyTargetExists['exists'][$i]);
                    if ($copyResult['result']) ++$copySuccessCount;
                }
                $allCopySuccess = ($copySuccessCount === $end);
            }
        } else $allCopySuccess = false;

        # 복사가 필요하여 복사를 완료했거나, 복사가 불필요한 경우 삭제
        $needDelete = ($needCopy && $allCopySuccess) || !$needCopy;
        Dev::print('allCopySuccess', $allCopySuccess);
        Dev::print('needDelete', $needDelete);
        if ($needDelete) {
            $deleteList = [];
            for ($i = 0, $end = count($source); $i < $end; ++$i) {
                $deleteList = array_merge($deleteList, $source[$i]);
            }
            $deleteResponse = $this->delete($deleteList);
            $allDeleteSuccess = $deleteResponse['result'];
        } else $allDeleteSuccess = false;

        $updateValue = [];
        if ($needCopy && $allCopySuccess) $updateValue[] = 'B';
        if ($needDelete && $allDeleteSuccess) $updateValue[] = 'D';
        $updateValue = implode('', $updateValue);

        $sql = "UPDATE (상품백업테이블) SET size_option='{$updateValue}' WHERE number = {$auctionProductRow['number']}";
        Dev::print('ResultQuery', $sql);
        DB::query($sql);
    }


    public function getSsh2()
    {
        if (empty($this->ssh2)) {
            $param = [
                'host' => '000.000.000.000',
                'port' => '22',
                'username' => '*******',
                'password' => '*******'
            ];
            $this->ssh2 = new Ssh2($param);
        }
        return $this->ssh2;
    }


    public function copy($source)
    {
        try {
            $log = [];
            $log['result'] = false;

            $destinationDefaultPath = '/__DESTINATION_DIR__';

            $pathInfo = pathinfo($source);
            if (is_int(strpos($pathInfo['dirname'], 'gimgs/'))) {
                $destination = str_replace('/__DOCUMENT_ROOT__', $destinationDefaultPath, $source);
            } else {
                $destination = str_replace('/__DOCUMENT_ROOT__/__IMAGE__DIR__', $destinationDefaultPath, $source);
            }

            $log['source'] = $source;
            $log['destination'] = $destination;

            if (file_exists($destination)) throw new Exception('already copied', 1);

            $destinationPathInfo = pathinfo($destination);
            if (!is_dir($destinationPathInfo['dirname'])) {
                umask(0);
                mkdir($destinationPathInfo, 0777, true);
            }

            ++$this->copyCount;
            $log['result'] = $this->getSsh2()->scp_recv($source, $destination);
        } catch (Exception $e) {
            $log['message'] = $e->getMessage();
            $log['result'] = (bool)$e->getCode();
        }
        Dev::print('copy', $log);
        return $log;
    }

    public function fileExists($list)
    {
        if (empty($this->curlForFileExists)) {
            $this->curlForFileExists = curl_init('http://192.168.1.5/fileExists.php');
            curl_setopt_array($this->curlForFileExists, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
            ]);
        }
        curl_setopt($this->curlForFileExists, CURLOPT_POSTFIELDS, http_build_query(['fileName' => $list]));
        return json_decode(curl_exec($this->curlForFileExists), true);
    }

    public function delete($list)
    {
        if (empty($this->curlForDelete)) {
            $this->curlForDelete = curl_init('http://192.168.1.5/delete.php');
            curl_setopt_array($this->curlForDelete, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
            ]);
        }
        curl_setopt($this->curlForDelete, CURLOPT_POSTFIELDS, http_build_query(['deleteTarget' => $list]));
        $response = curl_exec($this->curlForDelete);
        $responseArray = json_decode($response, true);
        Dev::print('Delete Response', $responseArray);
        $this->deleteCount += $responseArray['unlinkCount'];
        return $responseArray;
    }

    static function getSourceFromAuctionProductRow($auctionProductRow)
    {
        $imagePathList = [];
        for ($i = 1, $end = 10; $i <= $end; ++$i) {
            if (empty($auctionProductRow["img{$i}"])) continue;
            $imagePathList[] = $auctionProductRow["img{$i}"];
        }

        $source = [];
        for ($i = 0, $end = count($imagePathList); $i < $end; ++$i) {
            $imagePath = $imagePathList[$i];
            $sourceRow = self::getRelativePathFromImagePath($imagePath, $auctionProductRow['product_code']);
            $source[] = $sourceRow;
        }
        return $source;
    }

    static function getRelativePathFromImagePath($imagePath, $productCode)
    {
        $relativePathList = [];

        if ($imagePath[0] === '/') {
            $imagePathType = 'gimgs';
            $imagePath = substr($imagePath, 1);
        } else {
            $imagePathType = 'date';
        }

        $pathInfo = pathinfo($imagePath);

        $defaultPath = '/__DOCUMENT_ROOT__/';
        $fileAttach = '__IMAGE_DIR__/';
        $fileAttachCoop = '__COOP_IMAGE_DIR__/';
        $fileAttachThumb = '__THUMB_IMAGE_DIR__/';
        if ($imagePathType === 'gimgs') {
            $relativePathList[] = "{$defaultPath}/www/{$pathInfo['dirname']}/{$pathInfo['basename']}";

            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_30x30_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_34x34_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_40x40_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_50x50_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_80x80_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_90x90_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_100x100_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_120x120_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_170x170_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_288x290_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_600x600_100_2.{$pathInfo['extension']}";

            $tempDirName = str_replace('gimgs/', '', $pathInfo['dirname']); // 500_1
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/auction/130_gimgs/{$tempDirName}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/auction/300_gimgs/{$tempDirName}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/auction/400_gimgs/{$tempDirName}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/auction/600_gimgs/{$tempDirName}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/gmarket/100_gimgs/{$tempDirName}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/gmarket/600_gimgs/{$tempDirName}/{$pathInfo['basename']}";
        } else {
            $relativePathList[] = "{$defaultPath}/{$fileAttach}/{$pathInfo['dirname']}/{$pathInfo['basename']}";

            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_30x30_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_34x34_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_40x40_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_50x50_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_80x80_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_90x90_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_100x100_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_120x120_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_170x170_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_288x290_100_2.{$pathInfo['extension']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachThumb}/{$pathInfo['dirname']}/{$pathInfo['filename']}_N_7_600x600_100_2.{$pathInfo['extension']}";

            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/auction/130_{$pathInfo['dirname']}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/auction/300_{$pathInfo['dirname']}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/auction/400_{$pathInfo['dirname']}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/auction/600_{$pathInfo['dirname']}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/gmarket/100_{$pathInfo['dirname']}/{$pathInfo['basename']}";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/gmarket/600_{$pathInfo['dirname']}/{$pathInfo['basename']}";

            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/interpark/{$pathInfo['dirname']}/{$productCode}_1.jpg";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/interpark/{$pathInfo['dirname']}/{$productCode}_2.jpg";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/interpark/{$pathInfo['dirname']}/{$productCode}_3.jpg";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/interpark/{$pathInfo['dirname']}/{$productCode}_4.jpg";
            $relativePathList[] = "{$defaultPath}/{$fileAttachCoop}/interpark/{$pathInfo['dirname']}/{$productCode}_5.jpg";
        }
        return $relativePathList;
    }


}