import { reactive } from 'vue';
import { createCheckoutState } from './checkout-state.js';

export const checkoutState = reactive(createCheckoutState());

window.addEventListener('beforeunload', (event) => {
  if (checkoutState.unresolved) {
    event.preventDefault();
    event.returnValue = '';
  }
});
