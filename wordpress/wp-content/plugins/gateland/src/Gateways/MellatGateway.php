<?php


namespace Nabik\Gateland\Gateways;


use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\RefundFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class MellatGateway extends BaseGateway implements RefundFeature, ShaparakFeature {

	protected string $name = 'بانک ملت';

	protected string $description = 'به پرداخت ملت';

	protected string $url = 'https://l.nabik.net/behpardakht';

	public function request( Transaction $transaction ): void {
		throw new \Exception( sprintf( "جهت استفاده از درگاه «%s» به نسخه حرفه‌ای ارتقا دهید.", esc_attr( $this->name ) ) );
	}

	public function inquiry( Transaction $transaction ): bool {
		return false;
	}

	public function redirect( Transaction $transaction ) {
	}

	public function refund( Transaction $transaction, int $amount, string $description ) {
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label' => 'شناسه ترمینال',
				'key'   => 'terminal_id',
			],
			[
				'label' => 'نام کاربری',
				'key'   => 'username',
			],
			[
				'label' => 'کلمه عبور',
				'key'   => 'password',
			],
		];
	}

	public static function messages( $errorCode ): string {
		$messages = [
			0   => 'تراکنش با موفقیت انجام شد.',
			11  => 'شماره کارت نامعتبر است.',
			12  => 'موجودی کافی نیست.',
			13  => 'رمز نادرست است.',
			14  => 'تعداد دفعات وارد کردن رمز بیش از حد مجاز است.',
			15  => 'کارت نامعتبر است.',
			16  => 'دفعات برداشت وجه بیش از حد مجاز است.',
			17  => 'کاربر از انجام تراکنش منصرف شده است.',
			18  => 'تاریخ انقضای کارت گذشته است.',
			19  => 'مبلغ برداشت وجه بیش از حد مجاز است.',
			21  => 'پذیرنده نامعتبر است.',
			23  => 'خطای امنیتی رخ داده است.',
			24  => 'اطلاعات کاربری پذیرنده نامعتبر است.',
			25  => 'مبلغ نامعتبر است.',
			31  => 'پاسخ نامعتبر است.',
			32  => 'فرمت اطلاعات وارد شده صحیح نمی باشد.',
			33  => 'حساب نامعتبر است.',
			34  => 'خطای سیستمی.',
			35  => 'تاریخ نامعتبر است.',
			41  => 'شماره درخواست تکراری است.',
			42  => 'یافت نشد Sale تراکنش.',
			43  => 'قبلا درخواستVerifyداده شده است.',
			44  => 'درخواستVerfiy یافت نشد.',
			45  => 'تراکنشSettle شده است.',
			46  => 'تراکنشSettle نشده است.',
			47  => 'تراکنشSettle یافت نشد.',
			48  => 'تراکنشReverse شده است.',
			49  => 'تراکنشRefund یافت نشد.',
			51  => 'تراکنش تکراری است.',
			54  => 'تراکنش مرجع موجود نیست.',
			55  => 'تراکنش نامعتبر است.',
			61  => 'خطا در واریز.',
			111 => 'صادر کننده کارت نامعتبر است.',
			112 => 'خطای سوییچ صادر کننده کارت.',
			113 => 'پاسخی از صادر کننده کارت دریافت نشد.',
			114 => 'دارنده کارت مجاز به انجام این تراکنش نیست.',
			412 => 'شناسه قبض نادرست است.',
			413 => 'شناسه پرداخت نادرست است.',
			414 => 'سازمان صادر کننده قبض نامعتبر است.',
			415 => 'زمان جلسه کاری به پایان رسیده است.',
			416 => 'خطا در ثبت اطلاعات.',
			417 => 'شناسه پرداخت کننده نامعتبر است.',
			418 => 'اشکال در تعریف اطلاعات مشتری.',
			419 => 'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است.',
			421 => 'IPنامعتبر است.',
		];

		return $messages[ $errorCode ] ?? 'خطا غیرمنتظره! لطفا با مدیر وب سایت تماس بگیرید.';
	}
}