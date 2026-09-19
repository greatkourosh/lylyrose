<?php

use Nabik\Gateland\Gateways\BaseGateway;
use Nabik\Gateland\Gateways\Features\FreeFeature;
use Nabik\GatelandPro\GatelandPro;

defined('ABSPATH') || exit;

wp_enqueue_style('custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION);
wp_enqueue_style('notyf-style', GATELAND_URL . 'assets/css/notyf.min.css', [], GATELAND_VERSION);

wp_enqueue_script( 'alpine' );
wp_enqueue_script('notyf-script', GATELAND_URL . 'assets/js/notyf.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('global-script', GATELAND_URL . 'assets/js/global.js', ['notyf-script'], GATELAND_VERSION, true);
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/add-gateway.js', [], GATELAND_VERSION, true);

wp_localize_script('global-script', 'gateland', [
    'root' => esc_url_raw(rest_url()),
    'nonce' => wp_create_nonce('wp_rest'),
]);
?>

<section x-data="gatelandAddGateway" class="gateland-container">

    <section class="bg-[#F9FAFB]  text-base text-gray-900 py-6 md:pl-5 pl-2.5">

        <div class="container">

            <div class="mb-6">
                <div class="mb-3">
                    <a href="?page=gateland-gateways" class="inline-flex items-center gap-2 hover:translate-x-0.5">
                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/arrow-back.svg">
                        <div class="text-sm text-primary-500 font-semibold">
                            بازگشت به لیست درگاه‌ها
                        </div>
                    </a>
                </div>
                <div class="font-semibold text-lg">
                    افزودن درگاه جدید
                </div>
            </div>

            <!--steps-->
            <div class="bg-white border border-gray-100 rounded-2xl py-8 md:px-8 px-4 mb-5">
                <div class="flex justify-center">

                    <!-- current -->
                    <div class="w-[140px] text-center">
                        <div class="relative z-10 flex justify-center mb-3">
                            <!-- circle -->
                            <div
                                    class="size-8 rounded-full"
                                    :class="{'shadow-[0_0_0_4px_#F0F7FF]': currentStep === 'add'}"
                            >
                                <div class="size-8 bg-primary-100 flex items-center justify-center rounded-full shadow-[0_1px_2px_0_#1018280F]">
                                    <template x-if="currentStep === 'add'">
                                        <div class="size-2 bg-primary-600 rounded-full"></div>
                                    </template>
                                    <template x-if="currentStep === 'setting'">
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/tick.svg">
                                    </template>
                                </div>
                            </div>

                            <!--line-->
                            <div class="w-1/2 absolute -z-10 top-[50%] -mt-[1px] left-0 h-[2px] bg-gray-200"></div>
                        </div>
                        <div class="text-sm font-semibold text-gray-700">
                            انتخاب درگاه
                        </div>
                    </div>

                    <!-- next -->
                    <div class="w-[140px] text-center">
                        <div class="relative z-10 flex justify-center mb-3">
                            <!-- circle -->
                            <div
                                    class="size-8 rounded-full"
                                    :class="{'shadow-[0_0_0_4px_#F0F7FF]': currentStep === 'setting'}"
                            >
                                <div
                                        class="size-8 bg-gray-100 flex items-center justify-center rounded-full shadow-[0_1px_2px_0_#1018280F]"
                                        :class="{'!bg-primary-100': currentStep === 'setting'}"
                                >
                                    <template x-if="currentStep === 'add'">
                                        <div class="size-2 bg-gray-200 rounded-full"></div>
                                    </template>
                                    <template x-if="currentStep === 'setting'">
                                        <div class="size-2 bg-primary-600 rounded-full"></div>
                                    </template>
                                </div>
                            </div>

                            <!--line-->
                            <div class="w-1/2 absolute -z-10 top-[50%] -mt-[1px] right-0 h-[2px] bg-gray-200"></div>
                        </div>
                        <div class="text-sm font-semibold text-gray-700">
                            تنظیمات درگاه
                        </div>
                    </div>

                </div>
            </div>

            <div class="relative bg-white border border-gray-100 rounded-2xl overflow-hidden py-8 md:px-8 px-4">

                <!-- loader -->
                <section
                        x-show="pageLoaderIsActive"
                        class="page-loader absolute top-0 left-0 h-full w-full z-20 bg-gray-300 bg-opacity-90 flex items-center justify-center p-4"
                >
                    <span class="loader"></span>
                </section>

                <!-- step 1: select gateway-->
                <div
                        x-show="currentStep === 'add'"
                        x-transition
                >
                    <div class="text-center mb-8">
                        <div class="text-gray-900 font-semibold mb-2">
                            انتخاب درگاه
                        </div>
                        <div class="text-gray-600 text-sm">
                            می‌توانید پلن مورد نظر خود را از لیست زیر انتخاب کنید.
                        </div>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-xl overflow-hidden mb-5">
                        <div class="flex items-center flex-wrap gap-3 p-4">
                            <div class="text-lg font-semibold order-first flex items-center flex-wrap gap-2 ml-auto">
                                درگاه‌ها

                                <!-- skeleton -->
                                <template x-if="tableLoaderIsActive">
                                    <div class="skeleton h-7 w-40 rounded-full"></div>
                                </template>

                                <template x-if="!tableLoaderIsActive">
                                    <div class="flex items-center gap-0.5 bg-blue-50 text-blue-700 text-xs rounded-full py-1 px-3">
                                        آی.پی شما:
                                        <span x-text="host_ip"></span>
                                        <button
                                                @click="gatelandCopyToClipboard($el, 'آی.پی')"
                                                :data-copy="host_ip"
                                                class="mr-1"
                                        >
                                            <img class="" src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/copy.svg">
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <template x-if="!isProActive && !tableLoaderIsActive">
                                <div class="flex items-center flex-wrap gap-3">
                                    <div class="md:w-auto bg-primary-50 rounded-full flex items-center gap-2 md:order-1 order-2  py-1 px-2.5">
                                        <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square.svg">
                                        <span class="text-sm text-primary-500">برای دسترسی به تمامی درگاه‌ها، به نسخه تجاری ارتقا دهید.</span>
                                    </div>
                                    <a href="https://l.nabik.net/gateland-pro?utm_source=add-gateway" target="_blank"
                                       class="border border-gray-300 hover:bg-primary-600 hover:text-white text-sm shadow-[0_1px_2px_0_#1018280D] rounded-[8px] md:order-2 order-1 py-2 px-5">
                                        ارتقا به نسخه حرفه‌ای
                                    </a>
                                </div>
                            </template>
                        </div>
                        <div class="border-y border-gray-300 py-3 px-4">
                            <!--skeleton-->
                            <template x-if="tableLoaderIsActive">
                                <div class="inline-flex md:gap-0 gap-2 max-w-full md:border md:rounded-[8px] text-sm text-nowrap font-semibold md:overflow-hidden overflow-auto hidden-scrollbar">
                                    <template x-for="item in [1,2,3,4]">
                                        <div class="skeleton w-32 h-10 md:rounded-none rounded-full"></div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="!tableLoaderIsActive">
                                <div class="inline-flex md:gap-0 gap-2 max-w-full md:border md:rounded-[8px] text-sm text-nowrap font-semibold md:overflow-hidden overflow-auto hidden-scrollbar">
                                    <button
                                            @click="tableFilters.type = null"
                                            class="flex items-center gap-2 md:border-0 md:!border-l border border-gray-300 hover:bg-gray-100 md:rounded-none rounded-full md:py-2.5 py-2 md:px-4 px-3.5"
                                            :class="{'bg-gray-100' : (tableFilters.type === null)}"
                                    >
                                        همه درگاه‌ها
                                        <span
                                                x-text="tableData.length"
                                                class="bg-primary-50 text-primary-700 rounded-full text-xs py-0.5 px-2"
                                        >
                                    </button>
                                    <button
                                            @click="tableFilters.type = 'ShaparakFeature'"
                                            class="flex items-center gap-2 md:border-0 md:!border-l border border-gray-300 hover:bg-gray-100 md:rounded-none rounded-full md:py-2.5 py-2 md:px-4 px-3.5"
                                            :class="{'bg-gray-100' : (tableFilters.type === 'ShaparakFeature')}"
                                    >
                                        درگاه‌های بانکی
                                        <span
                                                x-text="getCountGatewaysByType('ShaparakFeature')"
                                                class="bg-primary-50 text-primary-700 rounded-full text-xs py-0.5 px-2"
                                        >
                                    </button>
                                    <button
                                            @click="tableFilters.type = 'BNPLFeature'"
                                            class="md:border-0 md:!border-l  border border-gray-300 hover:bg-gray-100 md:rounded-none rounded-full md:py-2.5 py-2 md:px-4 px-3.5"
                                            :class="{'bg-gray-100' : (tableFilters.type === 'BNPLFeature')}"
                                    >
                                        درگاه‌های اعتباری
                                        <span
                                                x-text="getCountGatewaysByType('BNPLFeature')"
                                                class="bg-primary-50 text-primary-700 rounded-full text-xs py-0.5 px-2"
                                        >
                                    </button>
                                    <button
                                            @click="tableFilters.type = 'CardToCardFeature'"
                                            class="md:border-0  border border-gray-300 hover:bg-gray-100 md:rounded-none rounded-full md:py-2.5 py-2 md:px-4 px-3.5"
                                            :class="{'bg-gray-100' : (tableFilters.type === 'CardToCardFeature')}"
                                    >
                                        درگاه‌های کارت به کارت
                                        <span
                                                x-text="getCountGatewaysByType('CardToCardFeature')"
                                                class="bg-primary-50 text-primary-700 rounded-full text-xs py-0.5 px-2"
                                        >
                                    </button>
                                    <!--
									<button class="md:border-0 border hover:bg-gray-100 md:rounded-none rounded-full md:py-2.5 py-2 md:px-4 px-3.5">
										پرداخت‌یارها
									</button>
									-->
                                </div>
                            </template>
                        </div>

                        <!--table-->
                        <div class="overflow-auto custom-scrollbar">
                            <table class="w-full">
                                <thead class="text-sm text-gray-600 text-nowrap">
                                <tr>
                                    <td class="bg-gray-100 py-3 px-5">
                                        نام درگاه
                                    </td>
                                    <td class="bg-gray-100 py-3 px-5">
                                        نوع درگاه
                                    </td>
                                    <td class="bg-gray-100 py-3 px-5">
                                        امکانات
                                    </td>
                                    <td class="bg-gray-100 py-3 px-5">
                                       عملیات
                                    </td>
                                </tr>
                                </thead>

                                <tbody class="w-full">
                                <!-- skeleton -->
                                <template x-if="tableLoaderIsActive">
                                    <template x-for="row in [1,2,3,4,5]">
                                        <tr class="border-b bg-white border-gray-200">
                                            <td class="py-4 md:px-5 px-3">
                                                <div class="flex items-center gap-3 min-w-[250px]">
                                                    <div class="skeleton size-10 min-w-10 rounded-md"></div>
                                                    <div class="text-sm">
                                                        <div class="skeleton w-10 h-4 rounded-full mb-2"></div>
                                                        <div class="skeleton w-24 h-4 rounded-full"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 md:px-5 px-3">
                                                <div class="skeleton w-24 h-4 rounded-full"></div>
                                            </td>
                                            <td class="py-4 md:px-5 px-3">
                                                <div class="flex items-center flex-wrap sm:gap-2 gap-1">
                                                    <template x-for="row in [1,2]">
                                                        <div class="skeleton w-20 h-4 rounded-full"></div>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="py-4 md:px-5 px-3">
                                                <div class="skeleton w-36 h-10 rounded-md"></div>
                                            </td>
                                        </tr>
                                    </template>
                                </template>

                                <!-- table data -->
                                <template x-if="!tableLoaderIsActive">
                                    <template x-for="(row, index) in getFilteredData">
                                        <tr
                                                class="bg-white border-gray-200"
                                                :class="{'!bg-gray-100': (!isProActive && !row.features.includes('FreeFeature')), 'border-b': (index !== getFilteredData().length - 1)}"
                                                x-transition
                                                :key="index"
                                        >
                                            <td class="py-4 md:px-5 px-3">
                                                <div class="flex items-center gap-3 min-w-[250px]">
                                                    <a :href="row.url" target="_blank"
                                                       class="size-10 min-w-10 flex items-center justify-center">
                                                        <img class="max-w-full" :src="row.icon">
                                                    </a>
                                                    <a :href="row.url" target="_blank" class="block text-sm">
                                                        <div class="font-semibold text-gray-900 mb-0.5">
                                                                      <span x-text="row.name"><span>
                                                        </div>
                                                        <div class="text-gray-600">
                                                                      <span x-text="row.description"><span>
                                                        </div>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="py-4 md:px-5 px-3">
                                                <div class="text-sm text-gray-600">
                                                    <template x-if="row.features.includes('BNPLFeature')">
                                                        <span class="text-nowrap" x-text="getGatewayTypeLabel('BNPLFeature')"></span>
                                                    </template>
                                                    <template x-if="row.features.includes('ShaparakFeature')">
                                                        <span class="text-nowrap" x-text="getGatewayTypeLabel('ShaparakFeature')"></span>
                                                    </template>
                                                    <template x-if="row.features.includes('CardToCardFeature')">
                                                        <span class="text-nowrap" x-text="getGatewayTypeLabel('CardToCardFeature')"></span>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="py-4 md:px-5 px-3">
                                                <div class="flex items-center flex-wrap sm:gap-2 gap-1">
                                                    <template x-if="!row.features.includes('FreeFeature')">
                                                        <div class="bg-brand-gold text-gray-900 flex gap-1 items-center text-nowrap rounded-full text-xs px-2 py-1">
                                                            <div class="min-w-4 w-4">
                                                                <img class="w-full"
                                                                     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/special.svg">
                                                            </div>
                                                            <span>گیت‌لند حرفه‌ای</span>
                                                        </div>
                                                    </template>
                                                    <template
                                                            x-for="item in row.features.filter(feature => (feature !== 'BNPLFeature' && feature !== 'ShaparakFeature' && feature !== 'FreeFeature' && feature !== 'CardToCardFeature'))">
                                                        <div class="bg-blue-50 text-blue-700 flex gap-1 items-center text-nowrap rounded-full text-xs px-2 py-1">
                                                            <div class="min-w-4 w-4">
                                                                <img class="w-full"
                                                                     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/special.svg">
                                                            </div>
                                                            <span x-text="getGatewayTypeLabel(item)"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="row.features.includes('FreeFeature')">
                                                        <div class="bg-blue-50 text-blue-700 flex gap-1 items-center text-nowrap rounded-full text-xs px-2 py-1">
                                                            <div class="min-w-4 w-4">
                                                                <img class="w-full"
                                                                     src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/special.svg">
                                                            </div>
                                                            <span>درگاه رایگان</span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="py-4 md:px-5 px-3">
                                                <div class="min-w-36">
                                                    <template
                                                            x-if="(!isProActive && !row.features.includes('FreeFeature'))"
                                                    >
                                                        <button
                                                                disabled
                                                                class="disabled:opacity-70 bg-white flex gap-x-1.5 items-start text-gray-700 text-sm font-semibold text-nowrap border border-gray-300 shadow-[0_1px_2px_0_rgba(16,24,40,0.05)] rounded-[8px] px-4 py-2.5"
                                                        >
                                                            <img  src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus-square.svg">
                                                            افزودن درگاه
                                                        </button>
                                                    </template>
                                                    <template
                                                            x-if="!(!isProActive && !row.features.includes('FreeFeature'))"
                                                    >
                                                        <button
                                                                @click="selectedGateway = row; nextStep()"
                                                                class="bg-white hover:bg-gray-100 flex gap-x-1.5 items-start text-gray-700 text-sm font-semibold text-nowrap  border border-gray-300 shadow-[0_1px_2px_0_rgba(16,24,40,0.05)] rounded-[8px] px-4 py-2.5"
                                                        >
                                                            <img  src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/plus-square.svg">
                                                            افزودن درگاه
                                                        </button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </template>

                                </tbody>

                            </table>
                        </div>
                    </div>

                    <!-- alert -->
                    <div class="flex gap-3 items-start border border-primary-300 bg-primary-25 rounded-xl text-sm p-4">
                        <img class="w-4 min-w-4"
                             src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square.svg">
                        <div>
                            <div class="font-normal text-primary-700">
                                اگر درگاه مورد نظرتان در اینجا موجود نیست، کافیست از طریق
                                <a href="https://t.me/nabik_net">
                                    تلگرام
                                </a>
                                یا
                                <a href="https://nabik.net/" target="_blank">
                                    سایت
                                </a>
                                به ما پیام
                                بدهید.
                            </div>
                        </div>
                    </div>

                </div>

                <!-- step 2: setting gateway-->
                <div
                        x-show="currentStep === 'setting'"
                        x-transition
                >
                    <div class="text-center mb-8">
                        <div class="text-gray-900 font-semibold mb-2">
                            تنظیمات درگاه
                            <span x-text="selectedGateway.name"></span>
                        </div>
                        <div class="text-gray-600 text-sm"></div>
                    </div>

                    <!-- inputs -->
                    <div class="grid grid-cols-12 md:gap-6 gap-5 text-gray-700 mb-10">
                        <template x-for="input in gatewayOptions">
                            <div class="col-span-full">
                                <div>
                                    <label
                                            x-show="input.type !== 'checkbox'"
                                            x-text="input.label"
                                            class="block text-sm mb-2"
                                    ></label>

                                    <div>
                                        <template x-if="!input.type || input.type === 'text' || input.type === 'url'">
                                            <input
                                                    x-model="input.model"
                                                    :placeholder="input?.placeholder"
                                                    class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg py-2 px-3"
                                                    :class="{'!text-left': (input.type === 'url')}"
                                                    :dir="`${(input.type === 'url') ? 'ltr' : 'rtl'}`"
                                            >
                                        </template>

                                        <template x-if="input.type === 'textarea'">
                                            <textarea
                                                    x-model="input.model"
                                                    :placeholder="input?.placeholder"
                                                    rows="5"
                                                    class="w-full bg-white border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg resize-none py-2 px-3"
                                            >
                                            </textarea>
                                        </template>

                                        <template x-if="input.type === 'checkbox'">
                                            <div
                                                    @click="input.model = !input.model"
                                                    class="inline-flex gap-2 cursor-pointer text-sm"
                                            >
                                                <div
                                                        class="size-4 min-w-4 border border-gray-300 rounded duration-300"
                                                        :class="{'bg-primary-400 !border-primary-400' : input.model}"
                                                >
                                                    <svg class="w-full" viewBox="0 0 24 24" fill="none"
                                                         xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M20 6L9 17L4 12" stroke="white" stroke-width="2"
                                                              stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </div>
                                                <span x-text="input.label"></span>
                                            </div>
                                        </template>

                                        <template x-if="input.type === 'select'">
                                            <div class="gap-1 border border-gray-300 shadow-[0_1px_2px_0_#1018280D] bg-white rounded-lg">
                                                <!--dropdown-->
                                                <div
                                                        x-data="{open: false}"
                                                        class="relative h-full"
                                                >
                                                    <!--active value-->
                                                    <div
                                                            @click="open = !open"
                                                            @click.outside="open = false"
                                                            class="flex items-center gap-2 cursor-pointer py-2 px-3"
                                                    >

                                                        <template x-if="input.model">
                                                            <div x-text="input.model" class="min-w-10"></div>
                                                        </template>
                                                        <template x-if="!input.model">
                                                            <div>
                                                                <template x-if="input.placeholder">
                                                                    <div x-text="input.placeholder"
                                                                         class="min-w-10 opacity-75"></div>
                                                                </template>
                                                                <template x-if="!input.placeholder">
                                                                    <div class="min-w-10 opacity-75">
                                                                        انتخاب کنید
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </template>

                                                        <div
                                                                class="duration-300 mr-auto"
                                                                :class="{'rotate-180' : open}"
                                                        >
                                                            <svg width="12" height="8" viewBox="0 0 12 8" fill="none"
                                                                 xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M1 1.5L6 6.5L11 1.5" stroke="#667085"
                                                                      stroke-width="1.66667" stroke-linecap="round"
                                                                      stroke-linejoin="round"/>
                                                            </svg>
                                                        </div>
                                                    </div>

                                                    <!-- dropdown items-->
                                                    <div
                                                            class="max-h-0 w-[calc(100%+2px)] absolute z-[1] top-[calc(100%+4px)] -left-[1px] border border-gray-200 border-opacity-0 rounded overflow-auto custom-scrollbar duration-300"
                                                            :class="{'!max-h-40 !border-opacity-100 shadow bg-white z-[2]' : open}"
                                                    >
                                                        <div class="bg-white pt-0.5">
                                                            <template x-for="(item, index) in input.options">
                                                                <div
                                                                        @click="input.model = item"
                                                                        class="cursor-pointer hover:text-primary-300 duration-300 p-1.5 mx-1"
                                                                        :class="{'border-b' : (index+1 !=  input.options.length)}"
                                                                >
                                                                    <span x-text="item"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <!--error msg-->
                                    <div
                                            x-show="input.description"
                                            x-text="input.description"
                                            class="text-xs text-gray-600 pt-1.5 empty:pt-0"
                                    >
                                    </div>

                                    <!--error msg-->
                                    <div
                                            x-show="input.errorMsg"
                                            x-text="input.errorMsg"
                                            class="text-xs text-error-300 pt-1.5 empty:pt-0"
                                    >
                                    </div>
                                </div>

                            </div>
                        </template>

                    </div>

                    <!-- alert -->
                    <div class="flex gap-3 items-start border border-primary-300 bg-primary-25 rounded-xl text-sm p-4">
                        <img class="w-4 min-w-4"
                             src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/info-square.svg">
                        <div>
                            <div class="text-primary-700 font-semibold mb-0.5">
                                اولویت‌بندی درگاه‌ را فراموش نکنید!
                            </div>
                            <div class="font-normal text-primary-700 pr">
                                پس از افزودن درگاه جدید، به صفحه لیست درگاه‌ها بروید و اولویت آن را تنظیم کنید. درگاه
                                تازه اضافه‌شده به انتهای لیست اولویت منتقل می‌شود.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- pagination -->
                <div
                        x-show="currentStep === 'setting'"
                        class="flex items-center justify-center flex-wrap gap-4 mt-10"
                >
                    <button
                            @click="prevStep()"
                            class="flex items-center gap-2.5 font-semibold text-white hover:text-white bg-primary-500 hover:bg-primary-600 disabled:opacity-50 disabled:cursor-default rounded-lg px-3.5 py-2"
                    >
                        <div class="">
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/arrow_right.svg">
                        </div>
                        <span>مرحله قبل</span>
                    </button>

                    <!-- button submit -->
                    <button
                            @click="submit()"
                            class="flex items-center gap-2.5 font-semibold text-white hover:text-white bg-primary-500 hover:bg-primary-600 rounded-lg px-3.5 py-2"
                    >
                        <span>ثبت درگاه</span>
                        <div class="">
                            <img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/arrow_left.svg">
                        </div>
                    </button>
                </div>

            </div>

        </div>

    </section>

</section>
