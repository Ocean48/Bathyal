<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    teal: {
                        400: '<?= $appConfig["theme"]["primary_400"] ?? "#2dd4bf" ?>',
                        500: '<?= $appConfig["theme"]["primary_500"] ?? "#14b8a6" ?>',
                        600: '<?= $appConfig["theme"]["primary_600"] ?? "#0d9488" ?>',
                    },
                    cyan: {
                        500: '<?= $appConfig["theme"]["primary_500"] ?? "#06b6d4" ?>',
                        600: '<?= $appConfig["theme"]["primary_600"] ?? "#0891b2" ?>',
                        700: '<?= $appConfig["theme"]["primary_600"] ?? "#0e7490" ?>',
                    },
                    slate: {
                        800: '<?= $appConfig["theme"]["sidebar_800"] ?? "#1e293b" ?>',
                        900: '<?= $appConfig["theme"]["sidebar_900"] ?? "#0f172a" ?>',
                    }
                }
            }
        }
    }
</script>
