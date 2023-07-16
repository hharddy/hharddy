<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\Data\Currency;
use App\Models\Data\Person;
use App\Models\Data\Cash;
use App\Models\Data\Account;
use App\Models\Data\Properties;
use App\Models\Data\HesabMoeen;
use App\Models\Docs\Factor;

use App\Models\Docs\Document;
use App\Models\Docs\Doc;
use App\Models\Docs\FromToCash;
use App\Models\Docs\Cheque;

use App\Utility\Constant;
use App\Utility\MHelper;
use App\Utility\KSecurity;
use App\Utility\GenUtility;
use App\Utility\Setting;

class FromToCashesController extends Controller
{
	/**{
    /**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request)
	{
		if (!Setting::GetUserIDFromToken($request->header('token'), $error)) {
			return response()->json([
				$error
			], GenUtility::HTTP_ERROR_CODE_TOKEN); // http respone code
		}

		$fromToCashes = FromToCash::Actives()
			->Departments()
			->where('iShareHolderID', '=', '0')
			->where('iPersonnelID', '=', '0')
			->select('iDocNum', 'date', 'dAmount');

		$totalCount = 0;
		if($request)
		{
			Log::alert($request);
			if($request->bTemp)
				$fromToCashes = $fromToCashes->where('bTemp', $request->bTemp);
			if($request->iDepartmentID)
				$fromToCashes = $fromToCashes->where('iDepartmentID', $request->iDepartmentID);
			if($request->order)
				$fromToCashes = $fromToCashes->orderby($request->order);
			else
				$fromToCashes = $fromToCashes->orderby('date', 'desc');
			$totalCount = $fromToCashes->count();

			if($request->limit)
				$fromToCashes = $fromToCashes->limit($request->limit);
			if($request->page)
            	$fromToCashes = $fromToCashes->offset($request->page * $request->limit);
		}
		else
		{
			$totalCount = $fromToCashes->count();
			$fromToCashes = $fromToCashes->orderby('date', 'desc');
		}
		$fromToCashes = $fromToCashes->get();
		//Log::alert($fromToCashes);
		//dd('s');
		$colsName = array("سند", "تاریخ", "مبلغ");
        $colsSize = array(1, 1, 1);
        $colsProp = array(Constant::COL_NUMBER, Constant::COL_DATE, Constant::COL_MONEY);

        return response()->json([
            "status" => 1,
            "list" => $fromToCashes,
            "colsName" => $colsName,
            "colsSize" => $colsSize,
            "colsProp" => $colsProp,
			"totalCount" => $totalCount,
        ], GenUtility::HTTP_CODE_SUCCESS); // http respone code

		//return json_decode($fromToCashes);
		//return View('list.fromToCashes.fromToCashes', ['fromToCashes' => $fromToCashes]);
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create()
	{
		$fromToCash = new FromToCash;
		$fromToCash->iID = 0;
		return View('list.fromToCashes.create', ['fromToCash' => $fromToCash]);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		Log::alert($request);

		if (!Setting::GetUserIDFromToken($request->header('token'), $error)) {
			return response()->json([
				$error
			], GenUtility::HTTP_ERROR_CODE_TOKEN); // http respone code
		}


		if ($request->iCashID == 0 && $request->dCashAmount > 0) {
			return response()->json([
				"status" => 0,
				"message" => "لطفا یک صندوق را انتخاب کنید"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}

		$iFromToCashID = 0;
		$iProjectID = 0;
		$date = Carbon::now();
		$bEditMode = false;
		$sMessage = "";
		$iDocNum = 0;
		$bTemp = $request->bTemp;
		if ($bEditMode)
			$iDocNum = $request->iDocNum;
		//if (!Document::CanAdd_Edit_Del_Doc($rFactor->iDocNum, 0, 0, !$bEdit, $bEdit, false, false, $rFactor->date, $sMessage))
		//if(!Document::CanAdd_Edit_Del_Doc(m_editDocNum.GetInt(), m_editDocNum.GetInt(), $iFiscalYear, !$bEditMode, $bEditMode, false, false, &date))
		if (!Document::CanAdd_Edit_Del_Doc(0, 0, 0, !$bEditMode, $bEditMode, false, false, $date, $sMessage)) {
			Log::alert("Document::CanAdd_Edit_Del_Doc:>" . $sMessage);
			//return false;
		}

		$sDescRow = "";
		$sDescRow_ = "";
		if ($sDescRow != "")
			$sDescRow_ = "[" . $sDescRow . "]";

		$iToDepartmentID = 0;
		$iHesabCode = 0;
		$iHesabCode2 = 0;
		$dHesabCode2Amount = 0;

		$iDepartmentID = 1;
		if (Setting::HasMultiDeps() && Setting::GetCurDepartmentID() && !Setting::IsPersonUser())
			$iDepartmentID = Setting::GetCurDepartmentID();
		$iPersonID = $request->iPersonID;
		$iUserID = Setting::GetUserID();

		
		//Log::alert("iUserID=" . $iUserID);
		//Log::alert("iUserPersonID=" . Setting::GetUserPersonID());

		if (Setting::IsPersonUser())
		{
			//Log::alert("aaa");
			$iPersonID = Setting::GetUserPersonID();
			//Log::alert($iPersonID);
			$iUserID = -1;
		}

		$iCashID = $request->iCashID;
		$iCodeTafsilyBelow = $request->iCodeTafsilyBelow;

		define('COUNT_PAYNUMBER', 3);
		//const COUNT_PAYNUMBER = 3;

		$iFiscalYear = Setting::HasFiscalYear() ? GenUtility::GetFiscalYear($date) : 0;

		if ($request->dCashAmount == 0)
			$iCashID = 0;

		if ($request->dAccountAmount == 0)
			$request->iAccountID = 0;
		if ($request->dAccountAmount2 == 0)
			$request->iAccountID2 = 0;
		if ($request->dAccountAmount3 == 0)
			$request->iAccountID3 = 0;

		$iAccountIDs = array($request->iAccountID, $request->iAccountID2, $request->iAccountID3);
		$iHesabTafsilyAccounts = array(0, 0, 0);
		$dAccountsAmount = array($request->dAccountAmount, $request->dAccountAmount2, $request->dAccountAmount3);
		$sPayNumbers = array($request->sPayNumber, $request->sPayNumber2, $request->sPayNumber3);

		$bPrevSettle = false;

		$iDocNum = 0;
		$str = $str2 = "";

		if ($iPersonID == 0 && $iHesabCode == 0 && $iToDepartmentID == 0) {
			return response()->json([
				"status" => 0,
				"message" => "لطفاً طرف حساب را مشخص نمایید"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}

		/*if($iPersonID && pDBSetting->m_cPropDocs.bCanSelectPersonTafsily && m_comboTafsilyCode.GetCurSel() == -1)
		{
		g_pDlgWarning->ShowText("حساب تفصیلی طرف حساب را مشخص نمایید")); 
		return;
		}*/

		//Log::alert("a1");

		if ($iHesabCode2 && $dHesabCode2Amount == 0) {
			return response()->json([
				"status" => 0,
				"message" => "لطفاً مبلغ حساب خاص را مشخص نمایید"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}
		if (!$iHesabCode2 && $dHesabCode2Amount > 0) {
			return response()->json([
				"status" => 0,
				"message" => "لطفاً حساب خاص را مشخص نمایید"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}


		//Log::alert("a11");

		/*if($bPayCash && $request->dCashAmount > 0)
	{
		if($staticCashAmount.GetDouble() < 0)
		{
			if(g_pDlgYesNo->ShowText("موجودی پرداخت کننده در حال حاضر منفی است!\nآیا می خواهید ادامه دهید؟")) == IDNO)
				return;
		}
		else if(m_editNewAmount.GetDouble() < 0)
		{
			if(g_pDlgYesNo->ShowText("با این انتقال موجودی پرداخت کننده منفی می شود،\nآیا می خواهید ادامه دهید؟")) == IDNO)
				return;
		}
	}*/

		$bPayCash = false;
		$dNeedSum = 0;
		$dChequesSum = $request->dSumCheques;
		$SumAll = $request->dSumCashAndCheques;
		$dMustSum = $dMustSumCheques = -1;
		$dDiscount = $request->dDiscount;
		if ($dMustSum > 0)
		{
			if ($SumAll + $dDiscount < $dMustSum)
			{
				$str = sprintf("جمع مبالغ باید حداقل %f شود.", $dMustSum);
				return response()->json([
					"status" => 0,
					"message" => $str
				], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
			}
		}

		//Log::alert("a12");

		if ($dMustSumCheques > -1)
		{
			if ($dMustSumCheques < $dChequesSum)
			{
				$str = sprintf("جمع مبالغ چک ها نباید بیش از %f شود.", $dMustSumCheques);
				return response()->json([
					"status" => 0,
					"message" => $str
				], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
			}
		}

		//Log::alert("a2");

		/*if ($dNeedSum > 0 && $SumAll > $dNeedSum)
	{
		if (!$bPayCash && g_pDlgYesNo->ShowText("آیا می خواهید بیشتر از مبلغ مورد نیاز دریافت کنید؟")) != IDYES)
		{
			return;
		}
		else if ($bPayCash && g_pDlgYesNo->ShowText("آیا می خواهید بیشتر از مبلغ مورد نیاز پرداخت کنید؟")) != IDYES)
		{
			return;
		}
	}*/

		if ($SumAll == 0.) {
			return response()->json([
				"status" => 0,
				"message" => "لطفاً مبلغ را مشخص نمایید"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}

		//Log::alert("a3");
		//Log::alert($iAccountIDs);
		//Log::alert($dAccountsAmount);

		for ($i = 0; $i < COUNT_PAYNUMBER; $i++) {
			if (!$iAccountIDs[$i] && $dAccountsAmount[$i] > 0) {
				return response()->json([
					"status" => 0,
					"message" => "لطفاً حساب را مشخص نمایید"
				], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
			}
		}

		/*if (pDBSetting->m_cPropOptions.bForcedCustomPersonInfo)
	{
	}*/
		//Log::alert("a31");

		$bAllowMoreThanCreditLimit = true;

		$cheques = $request->get('cheques');
		$iCountCheque = count($cheques);

		//Log::alert("a4");

		/*if (m_buttonPersonDetail.IsWindowVisible() && pDBSetting->m_cPropOptions.bForcedCustomPersonInfo && m_bCanTypeCustomPerson)
	{
		if ($sCustomPersonName.IsEmpty())
		{
			return response()->json([
				"status" => 0,
				"message" => "لطفا نام را تایپ نمایید"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}
		if ($sCustomPersonTel.IsEmpty())
		{
			return response()->json([
				"status" => 0,
				"message" => "لطفا شماره موبایل را تایپ نمایید"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}
		if (CSMSSerialPort::IsMobileNumValid($sCustomPersonTel) == Constant::ENUM_MOBILE_OPERATOR_NONE)
		{
			return response()->json([
				"status" => 0,
				"message" => "لطفا شماره موبایل را صحیح وارد نمایید"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}
	}*/ {
			$iChequeID = 0;

			for ($i = 0; $i < $iCountCheque; $i++) {
				/*if($bPayCash)
			{
				if(m_listCheque.GetItemText($i, $iCOL_CHEQ_TYPE).IsEmpty())
				{
					$str = sprintf("چک ردیف %d را مشخص کنید"), i + 1);
				}

				iChequeID = m_listCheque.GetItemNumber($i, $iCOL_CHEQ_ID);
				if($iChequeID)
				{
					if(!tblCheques.IsRecordExist("iID"), iChequeID))
					{
						$str = sprintf("چک ردیف %d وجود ندارد!"), i + 1);
						g_pDlgWarning->ShowText(str);
						return;
					}
					if($cheque->iStatus == Constant::ENUM_STATUS_INACTIVE)
					{
						$str = sprintf("چک ردیف %d باطل شده است!"), i + 1);
						g_pDlgWarning->ShowText(str);
						return;
					}
					if($cheque->bSpent)
					{
						if($cheque->iExpenseID)
						{
							$str = sprintf("چک ردیف %d در یک هزینه خرج شده است!"), i + 1);
							g_pDlgWarning->ShowText(str);
							return;
						}
						if($cheque->iFactorNumBuy)
						{
							$str = sprintf("چک ردیف %d در یک فاکتور خرج شده است!"), i + 1);
							g_pDlgWarning->ShowText(str);
							return;
						}
						if($cheque->iFromToCashID2 && $cheque->iFromToCashID2 != $iFromToCashID)
						{
							$str = sprintf("چک ردیف %d در یک سند پرداخت دیگر خرج شده است!"), i + 1);
							g_pDlgWarning->ShowText(str);
							return;
						}
					}
				}
			}
			else*/ {
					/*{
					$sAccountNum = $cheques[$i]['sChequeNum'];
					BlackListPersons tblBlackListPersons;
					if (!sAccountNum.IsEmpty() && tblBlackListPersons.IsRecordExist("sAccountNum"), sAccountNum))
					{
						if(tblBlackListPersons->bDoNotAccept)
						{
							g_pDlgWarning->ShowText("شما نمی توانید چکی با این شماره حساب دریافت نمایید، زیرا این شماره حساب در لیست سیاه قرار دارد.")); 
							return;
						}
						else if(g_pDlgYesNo->ShowText("این شماره حساب در لیست سیاه قرار دارد، آیا مطمئن هستید که می خواهید این چک را دریافت کنید؟")) == IDNO)
						{
							return;
						}
					}
				}*/
				}
				$cheque = Cheque::select('iDocNum')
					->where('sChequeNum', $cheques[$i]['sChequeNum'])
					->where('iStatus', 0)
					->first();
				if ($cheque)
				{
					$str = sprintf("شماره چک ردیف %d را از قبل در سیستم وارد شده است (سند شماره %d) لطفا اصلاح فرمایید.", $i + 1, $cheque->iDocNum); 
					/*if(pDBSetting->m_cPropDocs.bJustWatnRepeatedCheque)
				{
					if(g_pDlgYesNo->ShowText(str . " آیا می خواهید ادامه دهید؟")) == IDNO)
					{
						return;
					}
				}
				else*/
				{
					return response()->json([
						"status" => 0,
						"message" => $str
					], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
				}
				}
			}
		}

		//Log::alert("a5");

		if ($iCountCheque > 0) {
			if ($iToDepartmentID) {
				return response()->json([
					"status" => 0,
					"message" => "دریافت/پرداخت چک در حالت شعب هنوز پیاده سازی نشده است"
				], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
			}

			/*if (pDBSetting->m_cPropCashFromPerson.bMustSelectChequeToCash && m_comboChequesToCashes.GetCurSel() == 0)
		{
			g_pDlgMessage->ShowText("نزد صندوق را باید مشخص کنید."));
			return;
		}*/
		}

		$dBrokerageAmount = 0;

		if ($bPayCash && $dBrokerageAmount > 0 && $request->dAccountAmount2 == 0) {
			return response()->json([
				"status" => 0,
				"message" => "هزینه کارمزد فقط در واریز به حساب امکان پذیر است، لطفا اصلاح فرمایید."
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}

		/*if ($bEditMode && !Setting::IsCurrentUserMainAdmin() && pDBSetting->m_cPropPermissions.bCustomCashes)
	{
		if (!m_comboCashName.GetReadOnly() && $request->dCashAmount > 0)
		{
			KSecurity security(Constant::ENUM_TABLES_CASHES, m_comboCashName.GetSelectedItemData());
			if (security.GetModifySecurity() == false)
			{
				g_pDlgWarning->ShowText("شما اجازه ندارید، لطفا اصلاح کنید."));
				return;
			}
		}
		for ($i = 0; $i < COUNT_PAYNUMBER; $i++)
		{
			if (!m_comboAccountNames[$i].GetReadOnly() && $dAccountsAmount[$i] > 0)
			{
				KSecurity security(Constant::ENUM_TABLES_ACCOUNTS, m_comboAccountNames[$i].GetSelectedItemData());
				if (security.GetModifySecurity() == false)
				{
					g_pDlgWarning->ShowText("شما اجازه ندارید، لطفا اصلاح کنید."));
					return;
				}
			}
		}
	}*/

		$bSendSMS = false;
		$sName = $sNameComplete = $sPersonMobile = "";
		$sPersonName = "";
		$dPersonCreditLimit = $dPersonCreditLimitCheque = $dPersonCreditLimitAll = 0;

		$iPersonHesabTafsilyCode = 0;
		if ($iPersonID)
		{
			$person = Person::where('iID', $iPersonID)
				->select("bLikely", "bAllowMoreThanCreditLimit", "iHesabTafsilyCode", "iHesabTafsilyCode2", "sName", "sFamily", "bReal", "iType")
				->First();
			if ($person == null)
			{
				return response()->json([
					"status" => 0,
					"message" => "طرف حساب وجود ندارد!"
				], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
			}

			if($person->bLikely)
			{
				return response()->json([
					"status" => 0,
					"message" => "طرف حساب هنوز قطعی نشده است"
				], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
			}

			if($person->bReal)
				$sPersonName = $person->sName . " " . $sPersonName = $person->sFamily;
			else
				$sPersonName = $person->sName;
			$iPersonHesabTafsilyCode = $person->GetPersonTafsily_($bPayCash);
			//Log::alert($iPersonHesabTafsilyCode);
			$bAllowMoreThanCreditLimit = $person->bAllowMoreThanCreditLimit;
		}

		//Log::alert("a6");
			/*
		$iPersonHesabTafsilyCode = tblPersons.GetPersonTafsily($bPayCash, m_bBedehkar || ::IsEquals(m_dOldBedehkar, 0.), m_comboTafsilyCode.GetCurSel(), $iCodeTafsilyBelow);
		if ($iPersonHesabTafsilyCode == 0)
		{
			g_pDlgWarning->ShowText("کد حساب طرف حساب انتخاب شده صفر است!"));
			return;
		}
		if ($bPrevSettle && pDBSetting->m_cPropCustomTafsilyCoding.iSellPrevSettle != pDBSetting->m_cPropDocsCoding.iPersonMoeenCodeBed)
		{
			tblTemp.FormatAndExecuteQuery("SELECT iCode FROM HesabMoeen WHERE (bMoeenPersonsBed=1 OR bMoeenPersonsBes=1) AND iCode=%d", pDBSetting->m_cPropCustomTafsilyCoding.iSellPrevSettle);
			if(!tblTemp.IsEOF())
				iPersonHesabTafsilyCode = int(pDBSetting->m_cPropCustomTafsilyCoding.iSellPrevSettle) * $iTafsilyCodeCount + iPersonHesabTafsilyCode % $iTafsilyCodeCount;
		}
		if ($iPersonHesabTafsilyCode <= 0)
			return;

		sNameComplete	= tblPersons.GetCompleteName2();
		sPersonMobile	= $person->sMobiles[0];
		tblPersons.GetCredits(dPersonCreditLimit, dPersonCreditLimitCheque, dPersonCreditLimitAll, bAllowMoreThanCreditLimit);
*/
			/*if(!$bEditMode && pDBSetting->m_cPropSMS.bFeatureA && $person->bSMSAccounting && !sPersonMobile.IsEmpty())
		{
			bSendSMS = (pDBSetting->m_cPropSMS.bAutoAccountingGetCash && !$bPayCash) || (pDBSetting->m_cPropSMS.bAutoAccountingPayCash && $bPayCash);
		}*/
		//}
		/*else if ($iToDepartmentID)
	{
		Departments tblDepartments;
		if (!tblDepartments.IsRecordExist("iID"), $iToDepartmentID))
		{
			g_pDlgWarning->ShowText("شعبه وجود ندارد!"));
			return;
		}

		if($bPayCash)
			iPersonHesabTafsilyCode = tblDepartments.$iTafsilyCodeBed1;
		else
			iPersonHesabTafsilyCode = tblDepartments.$iTafsilyCodeBes1;
	}
	else if($iHesabCode)
	{
		iPersonHesabTafsilyCode = $iHesabCode;
	}*/

		/*if($iPersonID && !$bPayCash && !$bEditMode && $dChequesSum > 0)
	{
		$dCurChequesSum = 0;
		if(($dChequesSum > 0 && dPersonCreditLimitCheque > 0) || dPersonCreditLimitAll > 0)
		{
			dCurChequesSum = Person::GetSumUnPassedCheques($iPersonID, $date, pDBSetting->m_cPropDepartments.bCheckCreditLimitPerDep ? $iDepartmentID : -1);
		}

		if($dChequesSum > 0 && dPersonCreditLimitCheque > 0)
		{
			if(dCurChequesSum + dChequesSum > dPersonCreditLimitCheque)
			{
				if(!ShowCreditLimitFinished("با صدور این سند جمع مبلغ چک های پاس نشده این طرف حساب به شما، از حد اعتبار چکی ایشان بیشتر می شود."), bAllowMoreThanCreditLimit))
					return;
			}
		}

		if(dPersonCreditLimitAll > 0 && m_dPersonBedehkarAmount + dCurChequesSum - dChequesSum - ($dDiscount + $request->dCashAmount + m_editAccountAmounts[0].GetDouble() +
			$request->dAccountAmount2 + m_editAccountAmounts[2].GetDouble() + $dHesabCode2Amount) > dPersonCreditLimitAll)
		{
			if(!ShowCreditLimitFinished("با صدور این سند جمع مبلغ بدهکاری و چک های پاس نشده این طرف حساب به شما، از حد اعتبار مجموع ایشان بیشتر می شود."), bAllowMoreThanCreditLimit))
				return;
		}
	}*/

	//Log::alert("a7");
	$cash = null;
	$sCashName = "";

	$accounts = array(null, null, null);

	$iTafsilyCodeCount = 100000; //pDBSetting->m_cPropDocs.iTafsilyCodeCount

	if ($request->iCashID && $request->dCashAmount > 0.) {
		$cash = Cash::where('iID', $request->iCashID)->First();
		if ($cash == null) {
			return response()->json([
				"status" => 0,
				"message" => "صندوق وجود ندارد!"
			], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
		}
		$sCashName		= $cash->sName;
	}
	for ($i = 0; $i < COUNT_PAYNUMBER; $i++)
	{
		if ($iAccountIDs[$i] && $dAccountsAmount[$i] > 0.)
		{
			$accounts[$i] = Account::where('iID', $iAccountIDs[$i])
			->Select("iID", "iHesabTafsilyCode", "sBankName", "sAccountNum", "iAccountType", "bPos", "iDepartmentID", "iCurrencyID")
			->First();
			if ($accounts[$i] == null) {
				return response()->json([
					"status" => 0,
					"message" => "حساب بانکی وجود ندارد!"
				], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
			}
			//Log::alert($accounts[$i]);
			//Log::alert($accounts[$i]->CompName);
			$iHesabTafsilyAccounts[$i] = $accounts[$i]->iHesabTafsilyCode;
		}
	}
	//Log::alert($accounts[0]);
	//Log::alert($accounts[1]);
	//Log::alert($accounts[2]);

	//Log::alert("a8");

	DB::beginTransaction();
	$iFicshNumber = 0;

	if(!$bTemp)
	{
		if ($bEditMode)
		{
			Document::EditRecord($iDocNum, $iFiscalYear, $date, $iProjectID, $request->sDesc);
		}
		else
		{
			$sSubject = "";
			if ($bPayCash)
				$sSubject = "پرداخت";
			else
				$sSubject = "دریافت";
			if ($bPrevSettle)
				$sSubject .= " بیعانه";
			$iFicshNumber = FromToCash::GetNewFicshNumber($bPayCash ? Constant::ENUM_ACC_FISCH_TYPE_PAY : Constant::ENUM_ACC_FISCH_TYPE_GET);
			$sSubject .= " فیش " . $iFicshNumber;
			$iDocNum = Document::AddRecord($date, Constant::ENUM_DOC_TYPE_PAY_PERSON, $bPayCash ? Constant::ENUM_DOC_SUB_TYPE_PAY_PERSON_TO_MULTI : Constant::ENUM_DOC_SUB_TYPE_PAY_PERSON_FROM_MULTI, $sSubject, $iProjectID, true, $request->sDesc);
			//Log::alert("Document::AddRecord=" . $iDocNum);
		}
	}

	if (Setting::HasFiscalYear())
		$iFiscalYear = GenUtility::GetFiscalYear($date);
	{
		$fromToCash = null;
		if ($bEditMode)
		{
			$fromToCash = FromToCash::select("iID", $iFromToCashID);
			$bTemp = $fromToCash->$bTemp;
			if ($fromToCash == null)
			{
				Log::alert("DB::rollback()");
				DB::rollback();
				return;
			}

			/*if (pDBSetting->m_cPropSMS.bAutoEditDoc && $iPersonID)
		{
			if(!::IsEquals(dAmount, $fromToCash->dAmount) || $iPersonID != $fromToCash->iPersonID)
				bSendSMS = true;
		}*/

			FromToCash::Empty($iFromToCashID);

			//if($fromToCash->iPersonID != $iPersonID || $fromToCash->iCashID != iCashID || $fromToCash->iAccountID != iAccountID)
			{
				if(!$bTemp)
					Document::EmptyDoc($iDocNum, $iFiscalYear);
			}
			$iFicshNumber = $fromToCash->iFicshNumber;
		}
		else
		{
			$fromToCash = new FromTOCash;
			$fromToCash->bFromWeb = true;
			$fromToCash->bTemp = $bTemp;
			$fromToCash->iDepartmentID = $iDepartmentID;
			$fromToCash->bPayCash	= $bPayCash;
			$fromToCash->iDocNum	= $iDocNum;
			$fromToCash->iFicshNumber	= $iFicshNumber;
			$fromToCash->iUserID	= $iUserID;
			$fromToCash->bMulti		= true;
			$fromToCash->bSellPrevSettle = $bPrevSettle;
		}

		//Log::alert("a9");

		/*if (!$bPayCash)
	{
		for ($i = 0; $i < _COUNT_FROMTOCASH_PERSONNELS; $i++)
		{
			if (pDBSetting->m_cPropCashFromPerson.bShowPersonnels[$i])
			{
				$fromToCash->iPersonnelIDs[$i] = m_editPersonnels[$i].GetItemData();
				$fromToCash->dPersonnelPoorsants[$i] = m_editPoorsants[$i].GetDouble();
			}
		}
	}*/

		$fromToCash->dBrokerageAmount = $dBrokerageAmount;

		$fromToCash->iFiscalYear = $iFiscalYear;
		$fromToCash->iPersonID = $iPersonID;
		$fromToCash->iToDepartmentID = $iToDepartmentID;
		$fromToCash->iCodeTafsilyBelow = $iCodeTafsilyBelow;
		$fromToCash->iHesabCode	= $iHesabCode;
		$fromToCash->iHesabCode2 = $iHesabCode2;
		$fromToCash->dHesabCode2Amount = $dHesabCode2Amount;
		$fromToCash->iProjectID	= $iProjectID;
		$fromToCash->date		= $date;

		$fromToCash->iCashID	= $iCashID;
		$fromToCash->dCashAmount = $request->dCashAmount;
		/*for ($i = 0; $i < COUNT_PAYNUMBER; $i++)
	{
		$fromToCash->iAccountIDs[$i] = $iAccountIDs[$i];
		$fromToCash->dAccountAmounts[$i] = $dAccountsAmount[$i];
		$fromToCash->sPayNumbers[$i] = $sPayNumbers[$i];
	}*/

		$fromToCash->iAccountID = $iAccountIDs[0];
		$fromToCash->dAccountAmount1 = $dAccountsAmount[0];
		$fromToCash->sPayNumber = $sPayNumbers[0];

		$fromToCash->iAccountID2 = $iAccountIDs[1];
		$fromToCash->dAccountAmount2 = $dAccountsAmount[1];
		$fromToCash->sPayNumber2 = $sPayNumbers[1];

		$fromToCash->iAccountID3 = $iAccountIDs[2];
		$fromToCash->dAccountAmount3 = $dAccountsAmount[2];
		$fromToCash->sPayNumber3 = $sPayNumbers[2];

		$fromToCash->dDiscount		= $dDiscount;

		$fromToCash->dChequeAmount	= $dChequesSum;

		$fromToCash->dAmount	= $SumAll;
		$fromToCash->sDesc		= $request->sDesc;
		$fromToCash->sDescRow	= $sDescRow;

		if ($request->iFactorID && $request->iFactorID > 0)
		{
			$fromToCash->iTableID		= Constant::ENUM_TABLES_FACTORS;
			$fromToCash->iRecordID		= $request->iFactorID;
		}

		$fromToCash->sPersonName		= $request->sPersonName;
		$fromToCash->sPersonTel			= $request->sPersonTel;
		$fromToCash->sPersonAddress		= $request->sPersonAddress;
		$fromToCash->sPersonNationalCode= $request->sPersonNationalCode;
		$fromToCash->sPersonPostCode	= $request->sPersonPostCode;
		$fromToCash->sPersonTel2		= $request->sPersonTel2;

			/*if($bPayCash)
		{
			m_comboBankName.GetWindowText($fromToCash->sBankName);
			$fromToCash->sAccountNum = m_comboAccountNum.GetCurSelText();
			$fromToCash->sCardNo = m_comboCardNo.GetCurSelText();
		}*/

		$fromToCash->save();

		/*$iCurrencyIDPerson = $iPersonID > 0 ? Person::GetPersonCurrency($iPersonID) : Doc::GetHesabCurrency($iHesabCode);
		$dCurrencyRatePerson = Currency::GetFactorAtDate($iCurrencyIDPerson, $date);*/

		$iCurrencyIDPerson = 1;
		$dCurrencyRatePerson = 1.;

		if (!$bEditMode)
			$iFromToCashID = DB::getPdo()->lastInsertId();

		$sFactorNum = "";
		$sFicshNumber = "";
		$iFactorNum = 0;
		if ($iFactorNum)
			$sFactorNum = sprintf(" [فاکتور %d]", $iFactorNum);

		$bReverseDocDesc = false; //pDBSetting->m_cPropDocs.bReverseDocDesc

		$iGetDiscountHesabCodeFromToCash = Constant::HESAB_MOEEN_GOT_DISCOUNT;//pDBSetting->m_cPropDocsCoding.iGetDiscountHesabCodeFromToCash
		$iPayDiscountHesabCodeFromToCash = Constant::HESAB_MOEEN_PAID_DISCOUNT;//pDBSetting->m_cPropDocsCoding.iPayDiscountHesabCodeFromToCash

		if(!$bTemp)
		{
			$bSaveFicshNumberInDoc = false; //pDBSetting->m_cPropDocs.bSaveFicshNumberInDoc
			if ($bSaveFicshNumberInDoc)
				$sFicshNumber = sprintf(" [فیش %d]", $iFicshNumber);

			//$iHesabMoeenCode;
			if ($dDiscount > 0) {
				$iHesabCode_ = 0;
				if ($bPayCash) {
					$iHesabCode_ = $iGetDiscountHesabCodeFromToCash;
					if ($bReverseDocDesc) {
						$str	= "دریافت تخفیف از " . $sPersonName;
						$str2 = "دریافت تخفیف";
					} else {
						$str	= "دریافت تخفیف از " . $sPersonName;
						$str2 = "پرداخت تخفیف";
					}
				} else {
					$iHesabCode_ = $iPayDiscountHesabCodeFromToCash;
					if ($bReverseDocDesc) {
						$str	= "اعطای تخفیف به " . $sPersonName;
						$str2 = "اعطای تخفیف";
					} else {
						$str	= "اعطای تخفیف به " . $sPersonName;
						$str2 = "دریافت تخفیف";
					}
				}
				$str .= $sFicshNumber . $sFactorNum;
				$str2 .= $sFicshNumber . $sFactorNum;

				// - Takhfif -> Bedehkar/Bestankar
				Doc::AddRecord($iDocNum, $iFiscalYear, $iHesabCode_, $iPersonHesabTafsilyCode, $date, $str . $sDescRow_, !$bPayCash, $dDiscount * $dCurrencyRatePerson, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0);

				// - Person -> Bestankar/Bedehkar
				Doc::AddRecord($iDocNum, $iFiscalYear, $iPersonHesabTafsilyCode, $iHesabCode_, $date, $str2 . $sDescRow_, $bPayCash, $dDiscount * $dCurrencyRatePerson, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, $iCurrencyIDPerson, $dCurrencyRatePerson);
			}

			if ($request->dCashAmount > 0) {
				if ($bPayCash) {
					if ($bReverseDocDesc) {
						$str	= "پرداخت نقدی به " . $sPersonName;
						$str2 = "پرداخت نقدی از " . $sCashName;
					} else {
						$str	= "پرداخت نقدی به " . $sPersonName;
						$str2 = "دریافت نقدی از " . $sCashName;
					}
				} else {
					if ($bReverseDocDesc) {
						$str	= "دریافت نقدی از " . $sPersonName;
						$str2 = "دریافت نقدی به " . $sCashName;
					} else {
						$str	= "دریافت نقدی از " . $sPersonName;
						$str2 = "پرداخت نقدی به " . $sCashName;
					}
				}
				$str .= $sFicshNumber . $sFactorNum;
				$str2 .= $sFicshNumber . $sFactorNum;

				$iCurrencyIDCash = 1; //m_comboCashName.GetSelectedItemExtraData();
				$dCurrencyRateCash = 1.; //Currency::GetFactorAtDate($iCurrencyIDCash, $date);

				// - Cash -> Bedehkar/Bestankar
				Doc::AddRecord($iDocNum, $iFiscalYear, $cash->iHesabTafsilyCode, $iPersonHesabTafsilyCode, $date, $str . $sDescRow_, !$bPayCash, $request->dCashAmount * $dCurrencyRateCash, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, $iCurrencyIDCash, $dCurrencyRateCash);

				// - Person -> Bestankar/Bedehkar
				Doc::AddRecord($iDocNum, $iFiscalYear, $iPersonHesabTafsilyCode, $cash->iHesabTafsilyCode, $date, $str2 . $sDescRow_, $bPayCash, $request->dCashAmount * $dCurrencyRateCash, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, $iCurrencyIDPerson, $dCurrencyRatePerson);
			}

			for ($i = 0; $i < COUNT_PAYNUMBER; $i++)
			{
				if ($dAccountsAmount[$i] > 0)
				{
					if ($i == 0) {
						if ($bPayCash)
							$str = $bReverseDocDesc ? "پرداخت به " : "پرداخت به ";
						else
							$str = $bReverseDocDesc ? "دریافت از " : "دریافت از ";
						$str .= $sPersonName . " توسط کارت خوان" . $sFicshNumber;

						if ($bPayCash)
							$str2 = $bReverseDocDesc ? "پرداخت کارت خوان از " : "دریافت کارت خوان از ";
						else
							$str2 = $bReverseDocDesc ? "دریافت کارت خوان به " : "پرداخت کارت خوان به ";
					} else if ($i == 1) {
						if ($bReverseDocDesc)
							$str = $bPayCash ? "پرداخت از حساب به " : "دریافت از ";
						else
							$str = $bPayCash ? "واریز به حساب " : "دریافت از ";
						$str .= $sPersonName . $sFicshNumber;

						if ($bPayCash)
							$str2 = $bReverseDocDesc ? "پرداخت از حساب از " : "دریافت از حساب از ";
						else
							$str2 = $bReverseDocDesc ? "واریز به حساب به " : "واریز به حساب";
					} else if ($i == 2) {
						if ($bPayCash)
							$str = $bReverseDocDesc ? "پرداخت حواله به " : "پرداخت حواله از ";
						else
							$str = $bReverseDocDesc ? "دریافت حواله از " : "دریافت حواله از ";
						$str .= $sPersonName . $sFicshNumber;

						if ($bPayCash)
							$str2 = $bReverseDocDesc ? "پرداخت حواله از " : "دریافت حواله از ";
						else
							$str2 = $bReverseDocDesc ? "دریافت حواله به " : "پرداخت حواله به ";
					}
					//$str2 .= $accounts[$i]->CompName . $sFicshNumber;
					$asAccountType_Fa = array ("جاری", "پس انداز" , "قرض الحسنه" , "کوتاه مدت");
					$str2 .= " " . $accounts[$i]->sBankName . " " . $accounts[$i]->sAccountNum . " " . $asAccountType_Fa[$accounts[$i]->iAccountType] . $sFicshNumber;

					$iCurrencyIDAccount = 1;//m_comboAccountNames[$i] . GetSelectedItemExtraData();
					$dCurrencyRateAccount = 1.;//Currency::GetFactorAtDate($iCurrencyIDAccount, $date);

					// - Account -> Bedehkar/Bestankar
					Doc::AddRecord($iDocNum, $iFiscalYear, $iHesabTafsilyAccounts[$i], $iPersonHesabTafsilyCode, $date, $str . $sFactorNum . $sDescRow_, !$bPayCash, $dAccountsAmount[$i] * $dCurrencyRateAccount, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, $iCurrencyIDAccount, $dCurrencyRateAccount);

					// - Person -> Bestankar/Bedehkar
					Doc::AddRecord($iDocNum, $iFiscalYear, $iPersonHesabTafsilyCode, $iHesabTafsilyAccounts[$i], $date, $str2 . $sFactorNum . $sDescRow_, $bPayCash, $dAccountsAmount[$i] * $dCurrencyRateAccount, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, $iCurrencyIDPerson, $dCurrencyRatePerson);

					if ($i == 1 && $bPayCash && $dBrokerageAmount > 0.)
					{
						Doc::AddRecord($iDocNum, $iFiscalYear, $iHesabTafsilyAccounts[1], Constant::HESAB_MOEEN_EXPENCE_BROKERAGE, $date, "بابت هزینه کارمزد" . $sDescRow_, false, $dBrokerageAmount * $dCurrencyRateAccount, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, $iCurrencyIDAccount, $dCurrencyRateAccount);
						Doc::AddRecord($iDocNum, $iFiscalYear, Constant::HESAB_MOEEN_EXPENCE_BROKERAGE, $iHesabTafsilyAccounts[1], $date, "بابت هزینه کارمزد" . $sDescRow_, true, $dBrokerageAmount * $dCurrencyRateAccount, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, 1, 1.);
					}
				}
			}

			$sHesabCode2 = "";
			if ($dHesabCode2Amount > 0) {
				if ($bPayCash) {
					if ($bReverseDocDesc) {
						$str = "پرداخت به " . $sPersonName;
						$str2 = "پرداخت از " . $sHesabCode2;
					} else {
						$str = "پرداخت به " . $sPersonName;
						$str2 = "دریافت از " . $sHesabCode2;
					}
				} else {
					if ($bReverseDocDesc) {
						$str = "دریافت از " . $sPersonName;
						$str2 = "دریافت به " . $sHesabCode2;
					} else {
						$str = "دریافت از " . $sPersonName;
						$str2 = "پرداخت به " . $sHesabCode2;
					}
				}
				$str .= $sFicshNumber . $sFactorNum;
				$str2 .= $sFicshNumber . $sFactorNum;

				$iCurrencyID = Doc::GetHesabCurrency($iHesabCode2);
				$dCurrencyRate = Currency::GetFactorAtDate($iCurrencyID, $date);

				// - HesabCode2 -> Bedehkar/Bestankar
				Doc::AddRecord($iDocNum, $iFiscalYear, $iHesabCode2, $iPersonHesabTafsilyCode, $date, $str . $sDescRow_, !$bPayCash, $dHesabCode2Amount * $dCurrencyRate, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, $iCurrencyID, $dCurrencyRate);

				// - Person -> Bestankar/Bedehkar
				Doc::AddRecord($iDocNum, $iFiscalYear, $iPersonHesabTafsilyCode, $iHesabCode2, $date, $str2 . $sDescRow_, $bPayCash, $dHesabCode2Amount * $dCurrencyRate, $iProjectID, true, -1, Constant::ENUM_TABLES_NONE, 0, $iCurrencyIDPerson, $dCurrencyRatePerson);
			}
		}

		//Log::alert("a10");

		if ($dChequesSum > 0) {
			$dAmountC = 0.;
			$sChequeNum = "";
			$sChequeDesc = "";
			$iChequeID = 0;

			$iGotChequesInCashMoeenCode = Constant::HESAB_MOEEN_GOT_CHEQUES_IN_CASH_;//pDBSetting->m_cPropDocsCoding . iGotChequesInCashMoeenCode
			$iPaidDocumentMoeenCode = Constant::HESAB_MOEEN_PAID_DOCUMENT_;//pDBSetting->m_cPropDocsCoding . iPaidDocumentMoeenCode

			$iCodeHesab = $iGotChequesInCashMoeenCode;//Person::GetCustomPersonTafsilyCode($iPersonID, $iGotChequesInCashMoeenCode);
			$iCodeHesab2 = $iPaidDocumentMoeenCode;//Person::GetCustomPersonTafsilyCode($iPersonID, $iPaidDocumentMoeenCode);

			if (!$bPayCash) { // دریافت 
				for ($i = 0; $i < $iCountCheque; $i++) {
					$dAmountC = $cheques[$i]['dAmount'];
					$iChequeID = 0;

					if ($bEditMode) {
						//$iChequeID = m_listCheque.GetItemNumber($i, $iCOL_CHEQ_ID);
						if ($iChequeID) {
							$cheque = Cheque::where('iID', $iChequeID)->First();
							if ($cheque == null)
								$iChequeID = 0;
						}
					}
					if ($bEditMode && $iChequeID && ($cheque->GetStatus() != Constant::ENUM_CHEQUE_STATUS_NONE || $cheque->iCashID)) { // فاکتور تعیین وضعیت شده پس تاریخ آنرا به روز کن 
						//tblCheques.Edit();
						//						$cheque->iFactorNumSell	= iFactorNum;
						//$cheque->iDocNum	= iDocNum;
						$cheque->iProjectID	= $iProjectID;
						$cheque->iPersonID	= $iPersonID;
						$cheque->iHesabCodeTo = $iHesabCode;
						$cheque->sPersonName = $sPersonName;
						$cheque->dateCreate	= $date;
						//$cheque->sAccountNum= $cheques[$i]['sChequeNum'];
						//$cheque->sChequeNum	= $cheques[$i]['sChequeNum'];
						//$cheque->sBankName	= $cheques[$i]['sBankName'];
						//$cheque->sBankCode	= $cheques[$i]['sBankCode'];
						//$cheque->datePass	= $cheques[$i]['datePass'];
						//$cheque->dAmount	= dAmountC;
						//$Cheques->sDesc		= sDesc;
						//$cheque->bPaied		= false;

						if(array_key_exists('sSayad', $cheques[$i]))
							$cheque->sSayad		= $cheques[$i]['sSayad'];
						if(array_key_exists('bSayadSet', $cheques[$i]))
							$cheque->bSayadSet	= $cheques[$i]['bSayadSet'];
						$cheque->iBackNum	= $cheques[$i]['iBackNum'];

						$sChequeDesc = sprintf(" ش %s بانک %s به تاریخ %s", $cheque->sChequeNum, $cheque->sBankName, GenUtility::GetShamsi($cheque->datePass));

						$cheque->save();
					} else if ($bEditMode == false || ($bEditMode && $iChequeID == 0) || ($bEditMode && $cheque->GetStatus() == Constant::ENUM_CHEQUE_STATUS_NONE && $cheque->iCashID == 0)) {
						$cheque = new Cheque;
						$cheque->iUserID = $iUserID;
						$cheque->iDepartmentID = $iDepartmentID;
						$cheque->iFromToCashID	= $iFromToCashID;
						$cheque->iDocNum	= $iDocNum;
						$cheque->iFiscalYear = $iFiscalYear;
						$cheque->iFicshNumberGet = $iFicshNumber;
						$cheque->iProjectID	= $iProjectID;
						$cheque->iPersonID	= $iPersonID;
						$cheque->iHesabCodeTo = $iHesabCode;
						$cheque->sPersonName = $sPersonName;
						$cheque->dateCreate	= $date;
						$cheque->sAccountNum = $cheques[$i]['sAccountNum'];
						$cheque->sMainOwnerName	= $cheques[$i]['sMainOwnerName'];
						$cheque->sChequeNum	= $cheques[$i]['sChequeNum'];
						$cheque->sBankName	= $cheques[$i]['sBankName'];
						$cheque->sBankCode	= $cheques[$i]['sBankCode'];
						$cheque->iBankDepCode	= $cheques[$i]['iBankDepCode'];
						$cheque->datePass	= $cheques[$i]['datePass'];
						$cheque->dAmount	= $dAmountC;
						//$Cheques->sDesc		= $request->sDesc;
						$cheque->bPaied		= false;
						$cheque->bTemp		= $bTemp;
						if(array_key_exists('sSayad', $cheques[$i]))
							$cheque->sSayad		= $cheques[$i]['sSayad'];
						if(array_key_exists('bSayadSet', $cheques[$i]))
							$cheque->bSayadSet	= $cheques[$i]['bSayadSet'];

/*							if (m_comboChequesToCashes . IsWindowVisible() && m_comboChequesToCashes . GetCurSel() != -1)
							$cheque->iCashID	= m_comboChequesToCashes . GetSelectedItemData();*/

						$cheque->iBackNum	= $cheques[$i]['iBackNum'];

						$sChequeDesc = sprintf(" ش %s بانک %s به تاریخ %s", $cheque->sChequeNum, $cheque->sBankName, GenUtility::GetShamsi($cheque->datePass));

						$cheque->save();

						if ($iChequeID == 0)
							$iChequeID = DB::getPdo()->lastInsertId();
					}

/*						tblFromToCashCheques . AddNew();
					tblFromToCashCheques . $iFromToCashID = $iFromToCashID;
					tblFromToCashCheques . $iOrder		= i;
					tblFromToCashCheques . $iChequeID	= iChequeID;
					tblFromToCashCheques . Update();*/

					if(!$bTemp)
					{
						$str = sprintf("دریافت چک%s از %s ", $sChequeDesc, $sPersonName);
						if ($bReverseDocDesc)
							$str2 = "دریافت چک";
						else
							$str2 = "پرداخت چک";

						$str .= $sFicshNumber . $sFactorNum;
						$str2 .= $sFicshNumber . $sFactorNum;

						// - Daryafty -> Bedehkar
						Doc::AddRecord($iDocNum, $iFiscalYear, $iCodeHesab, $iPersonHesabTafsilyCode, $date, $str . $sDescRow_, true, $dAmountC, $iProjectID, true);

						// - Person -> Bestankar
						Doc::AddRecord($iDocNum, $iFiscalYear, $iPersonHesabTafsilyCode, $iCodeHesab, $date, $str2 . $sChequeDesc . $sDescRow_, false, $dAmountC, $iProjectID, true);
					}
				}
			} else { // پرداخت 
				/*	
				$iChequeBookID = 0;

				for($i = 0; $i < $iCountCheque; $i++)
				{
					$pRowProp = m_listCheque.GetRowProp($i);
					$iChequeID = m_listCheque.GetItemNumber($i, $iCOL_CHEQ_ID);
					if(m_listCheque.GetItemText($i, $iCOL_CHEQ_TYPE) == "متفرقه"))
					{
						if(tblCheques.IsRecordExist("iID"), $iChequeID))
						{
							$dAmountC	= $cheque->dAmount;
							$sChequeNum	= $cheque->sChequeNum;
							if(!pRowProp->bReadOnly || pRowProp->iReadOnlyReason == 1)
							{
								if(!$bEditMode && pDBSetting->m_cPropDocs.bSaveHistory)
								{
									$str = sprintf("در سند پرداخت تجمیعی ش %d خرج شد به %s"), $iDocNum, sPersonName));
									pDBSetting->AddUpdateHistoryRecord(Constant::ENUM_TABLES_CHEQUES, iChequeID, "خرج چک"), $str);
								}
								tblCheques.Edit();
								$Cheques->iPersonID2		= $iPersonID;
								$Cheques->iHesabCode2	= $iHesabCode;
								$cheque->dateSpend		= $date;
								$cheque->iFromToCashID2	= $iFromToCashID;
								$cheque->iDocNum2		= $iDocNum;
								$cheque->iFiscalYear2	= $iFiscalYear;
								$cheque->iFicshNumberPay= $iFicshNumber;
								$cheque->bSpent			= true;
								//$cheque->iCashID		= 0;
//								$cheque->sPersonNameFor	= sPersonName;
								$cheque->iBackNum	= $cheques[$i]['iBackNum'];
								tblCheques.Update();
							}
						}
						else
						{
							g_pDlgWarning->ShowDebugText("چک پیدا نشد."));
						}

						$str = sprintf("خرج چک ش %s به %s", $sChequeNum, $sPersonName);
						$str .= $sFicshNumber;


						// - Pardakhty -> Bestankar
						Doc::AddRecord($iDocNum, $iFiscalYear, iCodeHesab, iCodeHesab2, $date, $str . $sDescRow_, false, dAmountC, $iProjectID, true);

						// - Daryafty -> Bedehkar
						//$str = sprintf("دریافت چک %s"), sChequeNum);
						Doc::AddRecord($iDocNum, $iFiscalYear, iCodeHesab2, iCodeHesab, $date, $str . $sDescRow_, true, dAmountC, $iProjectID, true);

						sChequeDesc = sprintf(" ش %s"), sChequeNum));
						//sChequeDesc .= sFicshNumber;
					}
					else
					{
						$iAccountID = m_listCheque.GetItemData($i);
						dAmountC	= $cheques[$i]['dAmount'];
						sChequeNum	= $cheques[$i]['sChequeNum'];

						if(tblAccounts.IsRecordExist("iID"), iAccountID))
						{
							iChequeID = 0;

							if($bEditMode)
							{
								iChequeID = m_listCheque.GetItemNumber($i, $iCOL_CHEQ_ID);
								if($iChequeID)
								{
									if(tblCheques.IsRecordExist("iID"), iChequeID) == false)
										iChequeID = 0;
								}
							}
							if(!pRowProp->bReadOnly && $bEditMode && iChequeID && $cheque->GetStatus() != Constant::ENUM_CHEQUE_STATUS_NONE)
							{// فاکتور تعیین وضعیت شده پس تاریخ آنرا به روز کن 
								tblCheques.Edit();
//								$cheque->iFactorNumBuy	= iFactorNum;
								$Cheques->iProjectID	= $iProjectID;
								$Cheques->iPersonID	= $iPersonID;
								$cheque->iHesabCodeTo= $iHesabCode;
								$cheque->sPersonNameFor= sPersonName;
								$cheque->dateCreate	= date;
								//$cheque->sAccountNum= $cheques[$i]['sChequeNum'];
								//$cheque->sChequeNum	= $cheques[$i]['sChequeNum'];
								//$cheque->sBankName	= $cheques[$i]['sBankName'];
								//$cheque->sBankCode	= $cheques[$i]['sBankCode'];
								//$cheque->datePass	= $cheques[$i]['datePass'];
								//$cheque->dAmount	= dAmountC;
								//$Cheques->sDesc		= sDesc;
								//$cheque->bPaied		= false;

								sChequeDesc = sprintf(" ش %s بانک %s به تاریخ %s"), $cheque->sChequeNum), $cheque->sBankName), GenUtility::GetShamsi($cheque->datePass)));
								$cheque->iBackNum	= $cheques[$i]['iBackNum'];

								tblCheques.Update();
							}
							else if($bEditMode == false || ($bEditMode && iChequeID == 0) || ($bEditMode && iChequeID && $cheque->GetStatus() == Constant::ENUM_CHEQUE_STATUS_NONE))
							{
								tblCheques.AddNew();
								$cheque->iUserID = $iUserID;
								$cheque->iDepartmentID = $iDepartmentID;
								$cheque->iFromToCashID	= $iFromToCashID;
								$cheque->bPaied		= true;
								$cheque->iDocNum	= iDocNum;
								$cheque->iFiscalYear= iFiscalYear;
								$cheque->iFicshNumberPay= $iFicshNumber;
								$Cheques->iProjectID	= $iProjectID;
								$Cheques->iPersonID	= $iPersonID;
								$cheque->iHesabCodeTo= $iHesabCode;
								$cheque->sPersonNameFor= sPersonName;
								$cheque->dateCreate	= date;
								$cheque->datePass	= $cheques[$i]['datePass'];
								$cheque->sChequeNum	= sChequeNum;
								$cheque->sBankName	= tblAccounts.$sBankName;
								$cheque->sBankCode	= tblAccounts.$sDepartmentName;
								$cheque->dAmount	= dAmountC;
								//$Cheques->sDesc		= sDesc;
								$cheque->iAccountID	= iAccountID;
								$cheque->sAccountNum= tblAccounts.$sAccountNum;

								sChequeDesc = sprintf(" ش %s بانک %s به تاریخ %s"), $cheque->sChequeNum), $cheque->sBankName), GenUtility::GetShamsi($cheque->datePass)));

								iChequeBookID = 0;
								if(tblChequeBook.IsRecordExist("iNum"), $cheque->sChequeNum))
								{
									iChequeBookID = tblChequeBook.$iID;
									$cheque->iChequeBooksID	= tblChequeBook.$iChequeBookID;
									$cheque->iChequeBookID	= tblChequeBook.$iID;
								}
									$cheque->iBackNum	= $cheques[$i]['iBackNum'];

								tblCheques.Update();
								if($iChequeID == 0)
									iChequeID = DB::getPdo()->lastInsertId();

									if($iChequeBookID && tblChequeBook.IsRecordExist("iID"), iChequeBookID))
								{
									tblChequeBook.Edit();
									tblChequeBook.$iChequeID	= iChequeID;
									tblChequeBook->date		= date;
									tblChequeBook.Update();
								}
							}
						}
						else
							g_pDlgWarning->ShowDebugText("حساب بانکی پیدا نشد."));

						$str = sprintf("پرداخت چک%sبه %s"), sChequeDesc), sPersonName));
						$str .= sFicshNumber;
					}

					// - Pardakhty -> Bestankar
					Doc::AddRecord($iDocNum, $iFiscalYear, iCodeHesab2, $iPersonHesabTafsilyCode, $date, $str . $sDescRow_, false, dAmountC, $iProjectID, true);

					// - Person -> Bedehkar
					if($bReverseDocDesc)
						str = "پرداخت چک");
					else
						str = "دریافت چک");
					$str .= sChequeDesc;
					$str .= sFicshNumber;
					Doc::AddRecord($iDocNum, $iFiscalYear, $iPersonHesabTafsilyCode, iCodeHesab2, $date, $str . $sDescRow_, true, dAmountC, $iProjectID, true);

					tblFromToCashCheques.AddNew();
					tblFromToCashCheques.$iFromToCashID= $iFromToCashID;
					tblFromToCashCheques.$iOrder		= i;
					tblFromToCashCheques.$iChequeID	= iChequeID;
					tblFromToCashCheques.Update();
				}
*/
				}
			}
		}

		//Log::alert("a11");

		if ($request->iFactorID && $request->iFactorID > 0 && !$bTemp)
		{
			$factor = Factor::where('iID', $request->iFactorID)->first();
			if ($factor)
			{
				DB::update("UPDATE Factors SET iDocNumTasvieh=?, iFiscalYearTasvieh=? WHERE iID=? LIMIT 1", [$iDocNum, $iFiscalYear, $request->iFactorID]);

				$bGet = true;
				$sQuery = sprintf("SELECT SUM(dAmount+dDiscount) AS sum FROM FromToCash WHERE iTableID=%d AND iRecordID=%d AND bPayCash=0 AND iStatus=0", Constant::ENUM_TABLES_FACTORS, $request->iFactorID);
				$sum = DB::select($sQuery)[0]->sum;
				$dSumTasviehShodeh = 0.;
				if ($bGet)
					$dSumTasviehShodeh = $sum;
				else
					$dSumTasviehShodeh = -$sum;

				$sQuery = sprintf("SELECT SUM(dAmount+dDiscount) AS sum FROM FromToCash WHERE iTableID=%d AND iRecordID=%d AND bPayCash=1 AND iStatus=0", Constant::ENUM_TABLES_FACTORS, $request->iFactorID);
				$sum = DB::select($sQuery)[0]->sum;
				if ($bGet)
					$dSumTasviehShodeh -= $sum;
				else
					$dSumTasviehShodeh += $sum;

				if ($dSumTasviehShodeh > 0 && $dSumTasviehShodeh >= $factor->dNesieh)
				{
					if ($factor->iData1 == 1 || $factor->iData1 == 3)
					{
						/*$sDesc("این فاکتور قبلا تسویه شده است."));
						if (factor->iDocNumTasvieh)
							sDesc += "\r\nشماره سند تسویه : ") + ::IToStr(factor->iDocNumTasvieh);
						g_pDlgMessage->ShowText(sDesc);
						return FALSE;*/
					}
					else
					{
						DB::update("UPDATE Factors SET iData1=3 WHERE iID=? LIMIT 1", [$request->iFactorID]);
						/*factor.Edit();
						factor->iData1 = 3;
						factor.Update();

						$sDesc("این فاکتور قبلا تسویه شده است."));
						g_pDlgMessage->ShowText(sDesc);
						bRefreshItem = TRUE;
						return TRUE;*/
					}
				}
			}
			else
			{
				return response()->json([
					"status" => 0,
					"message" => "فاکتوری با این شناسه وجود ندارد"
				], GenUtility::HTTP_ERROR_CODE_NOT_FOUND); // http respone code
			}
		}

		DB::commit();

		//Log::alert("finish");

		return response()->json([
			"status" => 1,
			"message" => ($bEditMode ? " اصلاح شد" : " ثبت شد"),
			//"iNum" => $iNum,
			"iDocNum" => $iDocNum,
			//"iFactorID" => $factor->iID,
			//"iID" => $factor->iID
		], GenUtility::HTTP_CODE_SUCCESS); // http respone code


		/*$fromToCash = FromToCash::where('sName', '=', $request->sName)->first();
        if ($fromToCash)
            return $this->index();*/

		//dd('okkkkk');

		/*$fromToCash = new FromToCash;
        $fromToCash->iGrade = $request->iGrade;
        $fromToCash->sName = $request->sName;
        $fromToCash->sNameEng = $request->sNameEng;
        $fromToCash->sCountry = $request->sCountry;
        $fromToCash->sCompany = $request->sCompany;
        $fromToCash->sWebsite = $request->sWebsite;
        $fromToCash->iStatus = !$request->iStatus;
        $fromToCash->dateCreate = Carbon::now();
        $fromToCash->sDesc = $request->sDesc;
        $fromToCash->save();

        $fromToCash = FromToCash::create([
        'sName' => $request->sName,
        'iStatus' => !$request->iStatus,
        //'dateCreate' => Carbon::now(),
        'sDesc' => $request->sDesc
        ]);

        return $this->index();*/
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  \App\Models\FromToCash  $brand
	 * @return \Illuminate\Http\Response
	 */
	public function show($iID)
	{
		$fromToCash = FromToCash::where('iID', '=', $iID)->firstOrFail();
		//dd($stuff);
		return View('list.fromToCashes.fromToCash')->with('fromToCash', $fromToCash);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  \App\Models\FromToCash  $brand
	 * @return \Illuminate\Http\Response
	 */
	public function edit($iID)
	{
		//dd($iID);
		//$fromToCash = fromToCash::find($iID)->first();
		$fromToCash = fromToCash::where('iID', '=', $iID)->firstOrFail();
		//dd($fromToCash);
		return View('list.fromToCashes.create')->with('fromToCash', $fromToCash);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \App\Models\FromToCash  $brand
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $iID)
	{
		/*$fromToCash->iGrade = $request->iGrade;
        $fromToCash->sName = $request->sName;
        $fromToCash->sNameEng = $request->sNameEng;
        $fromToCash->sCountry = $request->sCountry;
        $fromToCash->sCompany = $request->sCompany;
        $fromToCash->sWebsite = $request->sWebsite;
        $fromToCash->iStatus = !$request->iStatus;
        $fromToCash->sDesc = $request->sDesc;
        $fromToCash->save();*/

		//dd($request->iStatus);

		$fromToCash = FromToCash::where('iID', '=', $iID)->update([
			'sName' => $request->sName,
			'iStatus' => $request->iStatus == null ? 1 : 0,
			'sDesc' => $request->sDesc
		]);

		//return $this->index();
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Models\FromToCash  $brand
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($iID)
	{
		$fromToCash = FromToCash::where('iID', '=', $iID)->firstOrFail();
		//dd($fromToCash);
		$fromToCash->delete();
		//return $this->index();
	}
}
