import EndorsementTab from "./components/EndorsementTab.vue";
import EndorsementFormModal from "./components/EndorsementFormModal.vue";
import EndorsementWizardSection from "./components/EndorsementWizardSection.vue";
import EndorsementWizardReview from "./components/EndorsementWizardReview.vue";

pkp.registry.registerComponent("EndorsementTab", EndorsementTab);
pkp.registry.registerComponent("EndorsementFormModal", EndorsementFormModal);
pkp.registry.registerComponent(
  "EndorsementWizardSection",
  EndorsementWizardSection
);
pkp.registry.registerComponent(
  "EndorsementWizardReview",
  EndorsementWizardReview
);

pkp.registry.storeExtend("workflow", (piniaContext) => {
  const workflowStore = piniaContext.store;
  const { useLocalize } = pkp.modules.useLocalize;
  const { t } = useLocalize();

  workflowStore.extender.extendFn("getMenuItems", (menuItems) => {
    return menuItems.map((menuItem) => {
      if (menuItem.key === "publication" && menuItem.items) {
        return {
          ...menuItem,
          items: [
            ...menuItem.items,
            {
              key: "publication_plauditPreEndorsement",
              label: t("plugins.generic.plauditPreEndorsement.preEndorsement"),
              state: {
                primaryMenuItem: "publication",
                secondaryMenuItem: "plauditPreEndorsement",
                title: t("plugins.generic.plauditPreEndorsement.preEndorsement"),
              },
            },
          ],
        };
      }
      return menuItem;
    });
  });

  workflowStore.extender.extendFn("getPrimaryItems", (primaryItems, args) => {
    if (
      args?.selectedMenuState?.primaryMenuItem === "publication" &&
      args?.selectedMenuState?.secondaryMenuItem === "plauditPreEndorsement"
    ) {
      return [
        {
          component: "EndorsementTab",
          props: { submission: args.submission },
        },
      ];
    }
    return primaryItems;
  });
});
