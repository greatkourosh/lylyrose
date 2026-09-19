<?php

use Nabik\Gateland\Models\Gateway;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION);
wp_enqueue_style('notyf-style', GATELAND_URL . 'assets/css/notyf.min.css', [], GATELAND_VERSION);

wp_enqueue_script( 'alpine' );
wp_enqueue_script('notyf-script', GATELAND_URL . 'assets/js/notyf.min.js', [], GATELAND_VERSION, true);
wp_enqueue_script('global-script', GATELAND_URL . 'assets/js/global.js', ['notyf-script'], GATELAND_VERSION, true);
wp_enqueue_script('page-script', GATELAND_URL . 'assets/js/pages/edit-gateway.js', [], GATELAND_VERSION, true);

wp_localize_script('global-script', 'gateland', [
    'root' => esc_url_raw(rest_url()),
    'nonce' => wp_create_nonce('wp_rest'),
]);
?>

<section x-data="gatelandEditGateway" class="gateland-container">

    <section class="bg-[#F9FAFB]  text-base text-gray-900 py-6 md:pl-5 pl-2.5">
        <template x-if="!fromLoaderIsActive">
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
                        تنظیمات درگاه
                        <span x-text="gateway?.name"></span>
                    </div>
                </div>

                <div>
                    <div class="grid grid-cols-12 md:gap-6 gap-5 text-gray-700 mb-10">

                        <template x-for="input in gateway?.options">
                            <div class="md:col-span-6 col-span-full">
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
                                                                        :class="{'border-b' : (index+1 !==  input.options.length)}"
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

                    <!-- buttons -->
                    <div class="flex items-center flex-wrap justify-end gap-3 text-sm text-nowrap">
                        <a
                                href="?page=gateland-gateways"
                                class="min-w-[120px] text-center border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow hover:bg-gray-50 py-2 px-3.5"
                        >
                            انصراف
                        </a>
                        <button @click="submit()" class="min-w-[120px] border bg-primary-500 border-primary-500 text-white font-semibold rounded-lg hover:shadow hover:bg-primary-600  py-2 px-3.5">
                            تایید و اعمال تغییرات
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="fromLoaderIsActive">
            <div class="container">
                <div class="mb-6">
                    <div class="mb-3">
                        <div class="skeleton w-52 h-6 rounded-md"></div>
                    </div>
                    <div class="skeleton w-36 h-7 rounded-md"></div>
                </div>

                <div>
                    <div class="grid grid-cols-12 md:gap-6 gap-5 text-gray-700 mb-10">
                        <template x-for="item in [1,2,3,4]">
                            <div class="md:col-span-6 col-span-full">
                                <div class="skeleton w-20 h-5 mb-2 rounded-lg"></div>
                                <div class="skeleton h-11 rounded-lg"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </section>

</section>
