import { forwardRef, useState } from 'react';
import { Pressable, type PressableProps, type StyleProp, type View, type ViewStyle } from 'react-native';

import { useSgcTheme } from '@/hooks/use-sgc-theme';

/**
 * `Pressable` con indicador de foco visible. Con teclado físico, mouse o un dispositivo
 * de conmutación (switch access) se ve qué elemento está seleccionado; con el dedo no cambia nada.
 */
export const PressableA11y = forwardRef<View, PressableProps>(function PressableA11y(
  { style, onFocus, onBlur, ...resto },
  ref,
) {
  const tema = useSgcTheme();
  const [enfocado, setEnfocado] = useState(false);

  return (
    <Pressable
      ref={ref}
      {...resto}
      onFocus={(evento) => {
        setEnfocado(true);
        onFocus?.(evento);
      }}
      onBlur={(evento) => {
        setEnfocado(false);
        onBlur?.(evento);
      }}
      style={(estado) => {
        const propio = typeof style === 'function' ? style(estado) : style;

        return [
          propio,
          enfocado && {
            outlineWidth: 2,
            outlineStyle: 'solid' as const,
            outlineOffset: 2,
            outlineColor: tema.info,
          },
        ] as StyleProp<ViewStyle>;
      }}
    />
  );
});
