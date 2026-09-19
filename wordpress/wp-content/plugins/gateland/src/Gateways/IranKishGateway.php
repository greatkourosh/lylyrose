<?php


namespace Nabik\Gateland\Gateways;


use JetBrains\PhpStorm\ArrayShape;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class IranKishGateway extends BaseGateway implements ShaparakFeature {

	protected string $name = 'ایران کیش';

	protected string $description = 'irankish.com';

	protected string $url = 'https://l.nabik.net/irankish';

	public function request( Transaction $transaction ): void {
		throw new \Exception( sprintf( "جهت استفاده از درگاه «%s» به نسخه حرفه‌ای ارتقا دهید.", esc_attr( $this->name ) ) );
	}

	public function inquiry( Transaction $transaction ): bool {
		return false;
	}

	public function redirect( Transaction $transaction ) {
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label' => 'شماره پایانه',
				'key'   => 'terminal_id',
			],
			[
				'label' => 'کلمه عبور',
				'key'   => 'password',
			],
			[
				'label' => 'شماره پذیرنده',
				'key'   => 'acceptor_id',
			],
			[
				'label' => 'کلید عمومی',
				'key'   => 'public_key',
				'type'  => 'textarea',
			],
		];
	}

	public static function messages( $errorCode ): string {
		$messages = [
			5   => 'از انجام تراکنش صرف نظر شد',
			3   => 'پذیرنده فروشگاهی نا معتبر است',
			64  => 'مبلغ تراکنش نادرستاست،جمع مبالغ تقسیم وجوه برابر مبلغ کل تراکنش نمی باشد',
			94  => 'تراکنش تکراری است',
			25  => 'تراکنش اصلی یافت نشد',
			77  => 'روز مالی تراکنش نا معتبر است',
			97  => 'کد تولید کد اعتبار سنجی نا معتبر است',
			30  => 'فرمت پیام نادرست است',
			86  => 'شتاب در حالSign Offاست',
			55  => 'رمز کارت نادرست است',
			40  => 'عمل درخواستی پشتیبانی نمی شود',
			57  => 'انجام تراکنش مورد درخواست توسط پایانه انجام دهنده مجاز نمی باشد',
			58  => 'انجام تراکنش مورد درخواست توسط پایانه انجام دهنده مجاز نمی باشد',
			63  => 'تمهیدات امنیتی نقض گردیده است',
			96  => 'قوانینسامانه نقض گردیده است ، خطای داخلی سامانه',
			2   => 'تراکنش قبلا برگشت شده است',
			54  => 'تاریخ انقضا کارت سررسید شده است',
			62  => 'کارت محدود شده است',
			75  => 'تعداد دفعات ورود رمز اشتباه از حد مجاز فراتر رفته است',
			14  => 'اطلاعات کارت صحیح نمی باشد',
			51  => 'موجودی حساب کافی نمی باشد',
			56  => 'اطلاعات کارت یافت نشد',
			61  => 'مبلغ تراکنش بیش از حد مجاز است',
			65  => 'تعداد دفعات انجام تراکنش بیش از حد مجاز است',
			78  => 'کارت فعال نیست',
			79  => 'حساب متصل به کارت بسته یا دارای اشکال است',
			42  => 'کارت/حساب مبدا/مقصد در وضعیت پذیرش نمی باشد',
			31  => 'عدم تطابق کد ملیخریداربا دارنده کارت',
			98  => 'سقف استفاده از رمز دوم ایستا به پایان رسیده است',
			901 => 'درخواست نا معتبر است (Tokenization)',
			902 => 'پارامترهای اضافی درخواست نامعتبر می باشد (Tokenization)',
			903 => 'شناسه پرداخت نامعتبر می باشد (Tokenization)',
			904 => 'اطلاعات مرتبط با قبض نا معتبر می باشد (Tokenization)',
			905 => 'شناسه درخواست نامعتبر می باشد (Tokenization)',
			906 => 'درخواست تاریخ گذشتهاست(Tokenization)',
			907 => 'آدرس بازگشت نتیجه پرداخت نامعتبر می باشد (Tokenization)',
			909 => 'پذیرنده نامعتبر می باشد(Tokenization)',
			910 => 'پارامترهای مورد انتظار پرداخت تسهیمی تامین نگردیده است(Tokenization)',
			911 => 'پارامترهای مورد انتظار پرداخت تسهیمی نا معتبر یا دارای اشکال می باشد(Tokenization)',
			912 => 'تراکنش درخواستی برای پذیرنده فعال نیست (Tokenization)',
			913 => 'تراکنش تسهیم برای پذیرنده فعال نیست (Tokenization)',
			914 => 'آدرس آی پی دریافتی درخواست نا معتبر می باشد',
			915 => 'شماره پایانه نامعتبر می باشد (Tokenization)',
			916 => 'شماره پذیرنده نا معتبر می باشد (Tokenization)',
			917 => 'نوع تراکنش اعلام شده در خواست نا معتبر می باشد (Tokenization)',
			918 => 'پذیرنده فعال نیست(Tokenization)',
			919 => 'مبالغ تسهیمی ارائه شده با توجه به قوانین حاکم بر وضعیت تسهیم پذیرنده،نا معتبر است (Tokenization)',
			920 => 'شناسه نشانه نامعتبر می باشد',
			921 => 'شناسه نشانه نامعتبر و یا منقضی شده است',
			922 => 'نقض امنیت درخواست(Tokenization)',
			923 => 'ارسال شناسه پرداختدر تراکنش قبض مجاز نیست(Tokenization)',
			928 => 'مبلغ مبادله شده نا معتبر می باشد(Tokenization)',
			929 => 'شناسه پرداخت ارائه شده با توجه به الگوریتم متناظر نا معتبر می باشد(Tokenization)',
			930 => 'کد ملی ارائه شده نا معتبر می باشد(Tokenization)',
		];

		return $messages[ $errorCode ] ?? 'خطا غیرمنتظره! لطفا با مدیر وب سایت تماس بگیرید.';
	}

	#[ArrayShape( [ 'data' => "string", 'iv' => "string" ] )]
	public function authenticationEnvelope( $pub_key, $terminalID, $password, $amount ): array {
		$data           = $terminalID . $password . str_pad( $amount, 12, '0', STR_PAD_LEFT ) . '00';
		$data           = hex2bin( $data );
		$AESSecretKey   = openssl_random_pseudo_bytes( 16 );
		$ivlen          = openssl_cipher_iv_length( $cipher = "AES-128-CBC" );
		$iv             = openssl_random_pseudo_bytes( $ivlen );
		$ciphertext_raw = openssl_encrypt( $data, $cipher, $AESSecretKey, $options = OPENSSL_RAW_DATA, $iv );
		$hmac           = hash( 'sha256', $ciphertext_raw, true );
		$crypttext      = '';

		openssl_public_encrypt( $AESSecretKey . $hmac, $crypttext, $pub_key );

		return [
			'data' => bin2hex( $crypttext ),
			'iv'   => bin2hex( $iv ),
		];
	}
}