<?php

namespace Brands\Controller\Admin;

use Micro\Form\Form;
use Micro\Grid\Grid;
use Micro\Http\Response;
use Micro\Http\Response\FileResponse;
use Micro\Http\Response\RedirectResponse;

use Nomenclatures\Model\Continents;
use Nomenclatures\Model\Types;
use Nomenclatures\Model\Countries;
use Brands\Model\Brands;
use Nomenclatures\Model\Statuses;
use Micro\Application\Controller\Crud;
use Micro\Database\Expr;
use Micro\Http\Response\JsonResponse;
use Nomenclatures\Model\Currencies;
use Brands\Model\Entity\Brand;

class Reports extends Crud
{
    protected $scope = 'admin';

	public function viewAction()
    {

    }

    public function pricesBrandsAction()
    {
        $filters = parent::handleFilters();

        if ($filters instanceof Response) {
            return $filters;
        }

        $countryId = (int) $this->request->getParam('countryId');

        $dateFrom = new \DateTime(date('01.01.Y'));
        $dateTo   = new \DateTime(date('31.12.Y'));

        if (isset($filters['dateFrom']) && $filters['dateFrom']) {
            try {
                $dateFrom = (new \DateTime($filters['dateFrom']));
            } catch (\Exception $e) {}
        }

        if (isset($filters['dateTo']) && $filters['dateTo']) {
            try {
                $dateTo = (new \DateTime($filters['dateTo']));
            } catch (\Exception $e) {}
        }

        $continents = (new Continents())->fetchCachedPairs(array('active' => 1), null, array('id' => 'ASC'));
        $countries  = (new Countries())->fetchCachedPairs(array('active' => 1), null, array('id' => 'ASC'));
        $countriesContinents = (new Countries())->fetchCachedPairs(array('active' => 1), ['id', 'continentId'], array('id' => 'ASC'));

        if (!isset($countries[$countryId])) {
            return new RedirectResponse(route(null, ['action' => 'prices-continents', 'countryId' => null]));
        }

        $currentCurrency = null;

        if (isset($filters['currency'])) {
            $currentCurrency = (new Currencies())->find((int) $filters['currency']);
        }

        $pricesByBrands = $this->container['db']->fetchAll('
            SELECT Brands.id, Brands.name, SUM(IFNULL(BrandsStatusesRel.price, 0)) as price
            FROM Brands
            INNER JOIN BrandsStatusesRel ON Brands.id = BrandsStatusesRel.brandId
            WHERE BrandsStatusesRel.date BETWEEN "' . $dateFrom->format('Y-m-d') . '" AND "' . $dateTo->format('Y-m-d') . '" AND Brands.countryId = ' . $countryId . '
            GROUP BY Brands.id
        ');

        $dummyBrand = new Brand();
        $dummyBrand->setCountryId($countryId);

        foreach ($pricesByBrands as $k => $v) {
            $pricesByBrands[$k]['price'] = $dummyBrand->getFormatedPrice($v['price'], $currentCurrency);
        }

        return [
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'pricesByBrands'  => $pricesByBrands,
            'continent'       => isset($countriesContinents[$countryId]) && isset($continents[$countriesContinents[$countryId]]) ? $continents[$countriesContinents[$countryId]] : '',
            'country'         => isset($countries[$countryId]) ? $countries[$countryId] : '',
            'currencySymbol'  => $currentCurrency ? $currentCurrency['symbol'] : $dummyBrand->getCurrencySymbol(),
        ];
    }

    public function pricesContinentsAction()
    {
        $filters = parent::handleFilters();

        if ($filters instanceof Response) {
            return $filters;
        }

        $form = new Form(package_path('Brands', 'Resources/forms/admin/reports-prices-continents-filters.php'));

        $form->populate($filters);

        $currentCurrency = null;

        if (isset($filters['currency'])) {
            $currentCurrency = (new Currencies())->find((int) $filters['currency']);
        }

        if (empty($currentCurrency)) {
            //$currentCurrency = (new Currencies())->find((int) config('currency.default'));
        }

        $continents    = (new Continents())->fetchCachedPairs(array('active' => 1), null, array('id' => 'ASC'));
        $countries     = array();
        $populations   = array();
        $countriesRows = (new Countries())->addWhere('active', 1)->addOrder('name', 'ASC')->getItems();

        if (isset($filters['continentId']) && !empty($filters['continentId'])) {
            $continents = array_intersect_key($continents, array_flip($filters['continentId']));
        }

        $dateFrom = date('Y-01-01');
        $dateTo   = date('Y-12-31');

        if (isset($filters['dateFrom']) && $filters['dateFrom']) {
            try {
                $dateFrom = (new \DateTime($filters['dateFrom']))->format('Y-m-d');
            } catch (\Exception $e) {}
        }

        if (isset($filters['dateTo']) && $filters['dateTo']) {
            try {
                $dateTo = (new \DateTime($filters['dateTo']))->format('Y-m-d');
            } catch (\Exception $e) {}
        }

        $pricesByCountries = $this->container['db']->fetchPairs('
            SELECT Brands.countryId, SUM(IFNULL(BrandsStatusesRel.price, 0))
            FROM Brands
            INNER JOIN BrandsStatusesRel ON Brands.id = BrandsStatusesRel.brandId
            WHERE date BETWEEN "' . $dateFrom . '" AND "' . $dateTo . '"
            GROUP BY Brands.countryId
        ');

        $dummyBrand = new Brand();

        foreach ($countriesRows as $countryRow) {

            if (isset($filters['countryId'])
                && !empty($filters['countryId'])
                && !in_array($countryRow['id'], $filters['countryId'])
            ) {
                continue;
            }

            if (empty($countryRow['continentId'])) {
                continue;
            }

            /**
             * Създаване на списъци от държави за континент
             */
            if (!isset($countries[$countryRow['continentId']])) {
                $countries[$countryRow['continentId']] = array();
            }

            $countries[$countryRow['continentId']][$countryRow['id']] = $countryRow->toArray();

            /**
             * Изчисляване на популацията за континент
             */
            if (!isset($populations[$countryRow['continentId']])) {
                $populations[$countryRow['continentId']] = 0;
            }

            $populations[$countryRow['continentId']] += $countryRow['population'];

            if (!isset($prices[$countryRow['continentId']])) {
                $prices[$countryRow['continentId']] = array();
            }

            $countryPrice = isset($pricesByCountries[$countryRow['id']]) ? round($pricesByCountries[$countryRow['id']], 2) : 0;
            $countryPrice = $dummyBrand->setCountryId($countryRow['id'])->getFormatedPrice($countryPrice, $currentCurrency);

            if (empty($countryPrice)) {
                $countryPrice = '0 ' . ($currentCurrency ? $currentCurrency['symbol'] : $dummyBrand->getCurrencySymbol());
            }

            $prices[$countryRow['continentId']][$countryRow['id']] = $countryPrice;
        }

        return [
            'form' => $form,
            'continents' => $continents,
            'populations' => $populations,
            'countries' => $countries,
            'prices' => $prices,
            'currentCurrency' => $currentCurrency,
            'filters' => $filters
        ];
    }

    public function allBrandsAction()
    {
        $filters = parent::handleFilters();

        if ($filters instanceof Response) {
            return $filters;
        }

        $ipp = max($this->ipp, $this->request->getParam('ipp', $this->ipp));
        $page = max(1, $this->request->getParam('page', 1));

        $selectedBrands = isset($filters['brands']) ? json_decode($filters['brands'], \true) : [];

        $form = new Form(package_path('Brands', 'Resources/forms/admin/reports-all-brands-filters.php'));

        $form->populate($filters);

        $brandsModel = new Brands();
        $brandsModel->filterClasses();

        if (!empty($selectedBrands)) {
            $brandsModel->addWhere('name', $selectedBrands);
        }

        $brandsModel->addOrder('name');

        $brandsSelect = $brandsModel->getJoinSelect();

        $brandsSelect->joinInner('NomCountries', 'NomCountries.id = Brands.countryId', []);

        if (isset($filters['countryId']) && !empty($filters['countryId'])) {
            $brandsSelect->where('Brands.countryId IN(?)', $filters['countryId']);
        }

        if (isset($filters['continentId']) && !empty($filters['continentId'])) {
            $brandsSelect->where('NomCountries.continentId IN(?)', $filters['continentId']);
        }

        $grid = new Grid($brandsModel, package_path('Brands', 'Resources/grids/admin/reports/all-brands.php'));

        $grid->getRenderer()->setView($this->view);

        $grid->setIpp($ipp);
        $grid->setPageNumber($page);

        $nomStatuses = new \Nomenclatures\Model\Statuses();
        $this->view->assign('nomStatuses', $nomStatuses->fetchCachedPairs());

        return ['form' => $form, 'selectedBrands' => $selectedBrands, 'grid' => $grid];
    }

    public function ajaxGetCategoriesAction()
    {
        $ids = explode(',', $this->request->getParam('continentId'));
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return new JsonResponse([]);
        }

        $cModel = new \Nomenclatures\Model\Countries();

        $res = [];

        foreach ($cModel->fetchPairs(['continentId' => $ids], \null, ['name' => 'asc']) as $k => $v) {
            $res[] = [
                'key' => $k,
                'value' => $v
            ];
        }

        return new JsonResponse($res);
    }

    public function brandsAction()
    {
        $filters = parent::handleFilters();

        if ($filters instanceof Response) {
            return $filters;
        }

        $form = new Form(package_path('Brands', 'Resources/forms/admin/reports-brands-filters.php'));

        $form->populate($filters);

        $brands = array();
        $brandImages = array();

        if (isset($filters['brandId'])) {

            $brandsModel = new Brands();
            $brandsModel->filterClasses();
            $brandsModel->addWhere('name', $filters['brandId']);

            if (isset($filters['date']) && $filters['date']) {
                try {
                    $date = new \DateTime($filters['date']);
                    $statusDate = $date->format('Y-m-d');
                    //$brandsModel->addWhere(new Expr('statusDate <= "' . $brandsModel->getAdapter()->quote($statusDate) . '"'));
                } catch (\Exception $e) {

                }
            }

            $brandsRows = $brandsModel->getItems();

            foreach ($brandsRows as $brandRow) {

                if (!isset($brands[$brandRow['countryId']])) {
                    $brands[$brandRow['countryId']] = array();
                }

                $brands[$brandRow['countryId']][$brandRow['typeId']] = $brandRow;

                if (!isset($brandImages[$brandRow['typeId']])) {
                    if ($brandRow->getThumb()) {
                        $brandImages[$brandRow['typeId']] = array(
                            'path'  => $brandRow->getThumb(),
                            'image' => 'uploads/brands/thumbs/' . $brandRow->getId() . '.' . pathinfo($brandRow->getImage(), PATHINFO_EXTENSION),
                            'real'  => 'uploads/brands/' . $brandRow->getId() . '.' . pathinfo($brandRow->getImage(), PATHINFO_EXTENSION),
                        );
                    }
                }
            }
        }

        if (empty($brands)) {
            return ['form' => $form, 'brands' => $brands];
        }

        $currentCurrency = null;

        if (isset($filters['currency'])) {
            $currencyModel = new Currencies();
            $currentCurrency = $currencyModel->find((int) $filters['currency']);
        }

        $nomContinents = new Continents();
        $continents = $nomContinents->fetchCachedPairs(array('active' => 1), null, array('id' => 'ASC'));

        $nomTypes = new Types();
        $types = $nomTypes->fetchCachedPairs(['active' => 1], null, ['id' => 'ASC']);

        $nomCountries = new Countries();
        $nomCountries->addWhere('active', '1');
        $nomCountries->addOrder('name', 'ASC');
        $countriesRows = $nomCountries->getItems();

        $countries = array();
        $populations = array();

        foreach ($countriesRows as $countryRow) {

            if (empty($countryRow['continentId'])) {
                continue;
            }

            /**
             * Създаване на списъци от държави за континент
             */
            if (!isset($countries[$countryRow['continentId']])) {
                $countries[$countryRow['continentId']] = array();
            }

            $countries[$countryRow['continentId']][$countryRow['id']] = $countryRow;

            /**
             * Изчисляване на популацията за континент
             */
            if (!isset($populations[$countryRow['continentId']])) {
                $populations[$countryRow['continentId']] = 0;
            }

            $populations[$countryRow['continentId']] += $countryRow['population'];
        }

        $nomStatus = new Statuses();
        $statuses = $nomStatus->fetchCachedPairs();
        $nomStatus->resetSelect(true);
        $statusesColors = $nomStatus->fetchCachedPairs(null, array('id', 'color'));

        return [
            'form' => $form,
            'continents' => $continents,
            'populations' => $populations,
            'types' => $types,
            'countries' => $countries,
            'brands' => $brands,
            'brandImages' => $brandImages,
            'statuses' => $statuses,
            'statusesColors' => $statusesColors,
            'filters' => $filters,
            'currentCurrency' => $currentCurrency
        ];
    }

    public function ajaxGetBrandsAction()
    {
        $response = array();

        $query = $this->request->getParam('query');

        if (mb_strlen($query, 'UTF-8') > 2) {
            $brandsModel = new Brands();
            $brandsModel->filterClasses();
            $brandsModel->addWhere('name', $query . '%');
            $brandsModel->getJoinSelect()->group('name')->order(new Expr('NULL'));
            foreach ($brandsModel->getItems() as $item) {
                $response[] = array('id' => $item['name'], 'name' => $item['name']);
            }
        }

        return new Response\JsonResponse($response);
    }

    public function exportAction()
    {
        $isAdmin = is_allowed('Brands\Controller\Admin\Reports@view');
        $maxRange = ($isAdmin ? 2 : 1);

        function addHeaderRow($excelActiveSheet, $index, $value = '', $size = 10, $bold = true, $horizontal = null)
        {
            if ($horizontal == null) {
                $horizontal = \PHPExcel_Style_Alignment::HORIZONTAL_CENTER;
            }

            $parts = explode(':', $index);

            $excelActiveSheet->setCellValue($parts[0], $value);

            if (isset($parts[1])) {
                $excelActiveSheet->mergeCells($index);
            }

            $excelActiveSheet->getStyle($index)->getFont()->setBold($bold);
            $excelActiveSheet->getStyle($index)->getFont()->setSize($size);
            $excelActiveSheet->getStyle($index)->getAlignment()->setHorizontal($horizontal);
            $excelActiveSheet->getStyle($index)->getAlignment()->setWrapText(true);
        }

        $brands = $this->brandsAction();

        if ($brands instanceof Response || empty($brands['brands'])) {
            return new Response\HtmlResponse();
        }

        $phpExcel = new \PHPExcel();
        $phpExcel->setActiveSheetIndex(0);

        $sheet = $phpExcel->getActiveSheet();

        $rowIndex = '1';
        $chars = range('A', 'Z');

        $countTypes = count($brands['types']);
        $count = $countTypes * $maxRange + 1;

        addHeaderRow($sheet, 'A' . $rowIndex . ':' . $chars[$count - 1] . $rowIndex, $brands['form']->brandId->getValue(), 30);
        $rowIndex++;

        foreach ($brands['continents'] as $continentId => $continent) {

            $cellIndex = 'A';
            addHeaderRow($sheet, $cellIndex . $rowIndex . ':' . $chars[$count - 1] . $rowIndex, $continent, 14);

            $rowIndex++;

            addHeaderRow($sheet, $cellIndex . $rowIndex, 'Държава', 12);

            $cellIndex++;
            addHeaderRow($sheet, $cellIndex . $rowIndex . ':' . $chars[array_search($cellIndex, $chars) + $countTypes - 1] . $rowIndex, 'Статус', 12);

            if ($isAdmin) {
                $cellIndex = $chars[array_search($cellIndex, $chars) + $countTypes - 1];
                $cellIndex++;
                addHeaderRow($sheet, $cellIndex . $rowIndex . ':' . $chars[array_search($cellIndex, $chars) + $countTypes - 1] . $rowIndex, 'Предприети действия', 12);
            }

            $rowIndex++;

            $cellIndex = 'A';
            addHeaderRow($sheet, $cellIndex . $rowIndex, "Общо държави: " . (isset($brands['countries'][$continentId]) ? count($brands['countries'][$continentId]) : 0) . "\nОбщо население: " . (isset($brands['populations'][$continentId]) ? number_format($brands['populations'][$continentId], 0, ".", " ") : 0), 10, true, \PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle($cellIndex . $rowIndex)->getAlignment()->setWrapText(true);
            $sheet->getColumnDimension($cellIndex)->setAutoSize(true);

            $cellIndex++;
            foreach (range(1, $maxRange) as $i) {
                foreach ($brands['types'] as $type) {
                    addHeaderRow($sheet, $cellIndex . $rowIndex, $type);
                    $sheet->getStyle($cellIndex . $rowIndex)->getAlignment()->setWrapText(true);
                    $sheet->getColumnDimension($cellIndex)->setWidth(20);
                    $cellIndex++;
                }
            }

            $rowIndex++;

            if (isset($brands['countries'][$continentId])) {

                foreach ($brands['countries'][$continentId] as $countryId => $country) {

                    $cellIndex = 'A';

                    $cellValue = $country['ISO3166Code'] . ' ' . $country['name'] . "\nНаселение: " . number_format($country['population'], 0, ".", " ");

                    $totalPrice = 0;
                    $brandEntity = null;
                    foreach ($brands['types'] as $typeId => $type) {
                        if (isset($brands['brands'][$countryId][$typeId])) {
                            $brandEntity = $brands['brands'][$countryId][$typeId];
                            $totalPrice += $brandEntity->getPrice();
                        }
                    }

                    if ($brandEntity && $totalPrice > 0) {
                        $cellValue .= "\nОбща цена: " . $brandEntity->getFormatedPrice($totalPrice, $brands['currentCurrency']);
                    }

                    $sheet->setCellValue($cellIndex . $rowIndex, $cellValue);
                    $sheet->getStyle($cellIndex . $rowIndex)->getAlignment()->setWrapText(true);

                    $cellIndex++;

                    foreach ($brands['types'] as $typeId => $type) {

                        $cellValue = "";

                        if (isset($brands['brands'][$countryId][$typeId])) {
                            $brandEntity = $brands['brands'][$countryId][$typeId];
                            if (isset($brands['statuses'][$brandEntity['statusId']])) {
                                $cellValue .= $brands['statuses'][$brandEntity['statusId']];
                                $date = $brandEntity['statusDate'];
                                if ($date) {
                                    $date = new \DateTime($date);
                                    $cellValue .= "\n" . $date->format('d.m.Y');
                                }
                                if ($brandEntity->getPrice()) {
                                    $cellValue .= "\nЦена: " . $brandEntity->getFormatedPrice(null, $brands['currentCurrency']);
                                }
                                if ($brandEntity->getStatusNote()) {
                                    $cellValue .= "\nКоментар: " . $brandEntity->getStatusNote();
                                }
                            }
                        }

                        $sheet->setCellValue($cellIndex . $rowIndex, $cellValue);
                        $sheet->getStyle($cellIndex . $rowIndex)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle($cellIndex . $rowIndex)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_TOP);
                        $sheet->getStyle($cellIndex . $rowIndex)->getAlignment()->setWrapText(true);

                        /* if (isset($brands['brands'][$countryId][$typeId])) {

                            $brandEntity = $brands['brands'][$countryId][$typeId];
                            $color = (isset($brands['statusesColors'][$brandEntity['statusId']]) ? $brands['statusesColors'][$brandEntity['statusId']] : '#FFFFFF');

                            if ($color) {

                                if ($color[0] == '#') {
                                    $color = array('rgb' => substr($color, 1));
                                } else {
                                    $color = array('argb' => $color);
                                }

                                $sheet->getStyle($cellIndex . $rowIndex)->getFill()->applyFromArray(
                                    array(
                                        'type'  => \PHPExcel_Style_Fill::FILL_SOLID,
                                        'startcolor' => $color
                                    )
                                );
                            }
                        } */

                        $cellIndex++;

                    }

                    if ($isAdmin) {
                        foreach ($brands['types'] as $typeId => $type) {

                            $cellValue = "";

                            if (isset($brands['brands'][$countryId][$typeId])) {
                                $cellValue .= $brands['brands'][$countryId][$typeId]['description'];
                            }

                            $sheet->setCellValue($cellIndex . $rowIndex, strip_tags(str_replace(array("<br />", "<br>"), "\n", $cellValue)));
                            $sheet->getStyle($cellIndex . $rowIndex)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_TOP);
                            $sheet->getStyle($cellIndex . $rowIndex)->getAlignment()->setWrapText(true);

                            $cellIndex++;
                        }
                    }

                    $rowIndex++;
                }
            }

            $rowIndex++;
        }

        $sheet->setSelectedCell('A1');

        $writer = \PHPExcel_IOFactory::createWriter ($phpExcel, 'Excel5');
        $writer->save('data/brand.xls');

        return new FileResponse('data/brand.xls');
    }
}