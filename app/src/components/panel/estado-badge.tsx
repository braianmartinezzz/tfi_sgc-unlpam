import { StyleSheet, View } from 'react-native';

import { Txt } from '@/components/panel/txt';
import { SgcRadius } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

export function EstadoBadge({ estado }: { estado: string }) {
  const tema = useSgcTheme();
  const colores = tema.estadoColor(estado);

  return (
    <View style={[styles.badge, { backgroundColor: colores.bg }]}>
      <Txt variante="etiqueta" style={{ color: colores.fg }}>
        {estado}
      </Txt>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    borderRadius: SgcRadius.pill,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
});
