import { Toast } from '@capacitor/toast';

export function showNativeToast(text) {
  Toast.show({
    text,
    duration: 'short',
    position: 'bottom',
  });
}
